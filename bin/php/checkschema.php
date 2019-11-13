<?php
/**
 * A CLI script which checks problems with data in the current schema
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2019
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
//require_once ( dirname( __FILE__ ) . '/../../classes/ezdbiautoloadhelper.php' );
//spl_autoload_register( array( 'ezdbiAutoloadHelper', 'autoload' ) );

$cli = eZCLI::instance();

$script = eZScript::instance( array(
    'description' => "Generate DB Integrity Report",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[schemafile:][schemaformat:][database:][displaychecks][displayrows][omitdefinitions][omitforeignkeys][omitcustomqueries][omitcheck:]',
    '',
    array(
        'schemafile' => 'Name of file with definition of db schema checks',
        'schemaformat' => 'Format of db schema checks definition file',
        'database' => 'DSN for database to connect to (default ez db)',
        'omitdefinitions' => 'When checking foreign keys, validate only the data, not the table structure',
        'omitforeignkeys' => 'Do not check foreign keys, validate the data only using custom queries',
        'omitcustomqueries' => 'Do not check using custom queries, validate only the foreign keys',
        'omitcheck' => 'Omit specific checks. Use `displaychecks` to list all check names. Can be multiple, comma separated',
        'displayrows' => 'Display the offending rows, not only their count',
        'displaychecks' => 'Display the list of checks instead of executing them'
    )
);
$script->initialize();

if ( !$options['displaychecks'] )
{
    $cli->output( 'Checking schema...' );
}

if ( $options['schemafile'] == '' )
{
    $options['schemafile'] = 'ezdbintegrity.ini';
}

if ( $options['schemaformat'] == '' )
{
    $options['schemaformat'] = 'ezini';
}

if ( $options['omitcheck'] != '' )
{
    $options['omitcheck'] = explode( ',', $options['omitcheck'] );
}

try
{

    $violations = array();
    $checker = new ezdbiSchemaChecker( $options['database'] );
    $checker->loadChecksFile( $options['schemafile'], $options['schemaformat'] );
    $checks = $checker->getChecks( $options['omitforeignkeys'], $options['omitcustomqueries'] );

    if ( is_array( $options['omitcheck'] ) && count( $options['omitcheck'] ) )
    {
        foreach ( $checks as $check => $def )
        {
            if ( in_array( $check, $options['omitcheck'] ) )
            {
                unset( $checks[$check] );
            }
        }
    }

    if ( function_exists( 'pcntl_signal' ) )
    {
        pcntl_signal(SIGTERM, 'onStopSignal');
        pcntl_signal(SIGINT, 'onStopSignal');
        saveState( array(
            'cli' => $cli,
            'script' => $script,
            'checks' => $checks,
            'violations' => &$violations,
            'options' => $options
        ) );
    }

    if ( $options['displaychecks'] )
    {
        // all has already been done
    }
    else
    {
        $i = 0;
        foreach ( array_keys( $checks ) as $check )
        {
            $cli->output( "\nNow checking $check ..." );
            $violation = $checker->check( $check, $options['displayrows'], $options['omitdefinitions'] );
            if ( count( $violation ) )
            {
                $violations[$check] = $violation;
                if ( $script->verboseOutputLevel() > 0 )
                {
                    $cli->output( ezdbiReportGenerator::getText( $violation, array() ) );
                }
            }

            if ( function_exists( 'pcntl_signal' ) )
            {
                pcntl_signal_dispatch();
            }
        }

        $cli->output( 'Done!' );
        $cli->output();
    }

    $cli->output( ezdbiReportGenerator::getText( $violations, $checks, $options['displaychecks'] ) );

    $script->shutdown();
}
catch( Exception $e )
{
    $cli->error( $e->getMessage() );
    $script->shutdown( -1 );
}

function onStopSignal( $sigNo )
{
    global $scriptState;

    $violations = $scriptState['violations'];
    $cli  = $scriptState['cli'];
    $checks = $scriptState['checks'];
    $options = $scriptState['options'];
    $script = $scriptState['script'];

    $cli->output( ezdbiReportGenerator::getText( $violations, $checks, $options['displaychecks'] ) );

    $script->shutdown();
    die();
}

// We can not just use $GLOBALS as sometimes the script is run within a class (in eZ5), sometimes not...
function saveState($stateArray)
{
    global $scriptState;

    $scriptState = $stateArray;
}
