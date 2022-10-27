<?php
/**
 * A CLI script which checks problems with all object attributes of a given datatype in current database
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
//require_once ( dirname( __FILE__ ) . '/../../classes/ezdbiautoloadhelper.php' );
//spl_autoload_register( array( 'ezdbiAutoloadHelper', 'autoload' ) );

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
////spl_autoload_register( array( 'autoloadHelper', 'autoload' ) );

$cli = eZCLI::instance();

$script = eZScript::instance( array(
    'description' => "Generate Datatype Integrity Report",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[unpublished][displaychecks]',
    '[datatype]',
    array(
        'datatype' => 'name of the datatype to check: Use "*" for all known types (with the quotes!!!). class/attribute is also ok',
        'unpublished' => 'If set, all object attributes will be checked (old versions, trash, drafts, etc)',
        'displaychecks' => 'Display the list of checks instead of executing them. Use this to list the datatypes available'
    )
);
$script->initialize();

if ( count( $options['arguments'] ) < 1 && ! $options['displaychecks'] )
{
    $script->shutdown( 1, 'Wrong argument count. Please run with --help to see command syntax' );
}

$type = @$options['arguments'][0];
if ( strpos( $type, '/' ) !== false )
{
    $argType = 'class attribute';
}
else
{
    $argType = 'datatype';
}

if ( !$options['displaychecks'] )
{
    $cli->output( "Checking $argType '$type'..." );
}

try
{
    $violations = array();
    $checker = new ezdbiDatatypeChecker();
    $checker->setCli( $cli );

    if ( $options['displaychecks'] )
    {
        $checker->loadDatatypeChecks();
        $checks = $checker->getChecks();
    }
    else
    {
        if ( $type == '*' )
        {
            // all datatypes we can check
            $checker->loadDatatypeChecks();
            $types = $checker->getChecks();
            $checks = $types;
        }
        else
        {
            // single datatype
            $types = array( $type => 1 );
            $checks = $checker->loadDatatypeChecksforType( $type );
            if ( $checks == false )
            {
                throw new Exception( "No checks defined for $argType $type" );
            }
        }

        if ( function_exists( 'pcntl_signal' ) )
        {
            pcntl_signal(SIGTERM, 'onStopSignalCA');
            pcntl_signal(SIGINT, 'onStopSignalCA');
            saveStateCA( array(
                'cli' => $cli,
                'script' => $script,
                'checks' => $checks,
                'violations' => &$violations,
                'options' => $options
            ) );
        }

        foreach( array_keys( $types ) as $type )
        {
            $cli->output( "\nNow checking $type ..." );
            $typeViolations = $checker->check( $type, $options['unpublished'] );
            if ( count( $typeViolations ) )
            {
                $violations[$type] = $typeViolations;
                if ( $script->verboseOutputLevel() > 0 )
                {
                    $cli->output( ezdbiReportGenerator::getText( $typeViolations, array() ) );
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

    /// @todo if the script is run with '-q' flag, this report will not be printed.
    ///       It would be nice to have everything else silenced, except this.
    $cli->output( ezdbiReportGenerator::getText( $violations, $checks, $options['displaychecks'] ) );

    $script->shutdown();
}
catch( Exception $e )
{
    $cli->error( $e->getMessage() );
    $script->shutdown( -1 );
}

function onStopSignalCA( $sigNo )
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
function saveStateCA($stateArray)
{
    global $scriptState;

    $scriptState = $stateArray;
}
