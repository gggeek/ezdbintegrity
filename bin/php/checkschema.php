<?php
/**
 * A CLI script which checks problems with data in the current schema
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2016
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
    '[schemafile:][schemaformat:][database:][displaychecks][displayrows][omitdefinitions]',
    '',
    array(
        'schemafile' => 'Name of file with definition of db schema checks',
        'schemaformat' => 'Format of db schema checks definition file',
        'database' => 'DSN for database to connect to (default ez db)',
        'omitdefinitions' => 'When checking foreign keys, validate only the data, not the table structure',
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

try
{
    $violations = array();
    $checker = new ezdbiSchemaChecker( $options['database'] );
    $checker->loadChecksFile( $options['schemafile'], $options['schemaformat'] );
    $checks = $checker->getChecks();

    if ( $options['displaychecks'] )
    {
    }
    else
    {
        foreach ( array_keys( $checks ) as $check )
        {
            $cli->output( "\nNow checking $check ..." );
            $violation = $checker->check( $check, $options['displayrows'], $options['omitdefinitions'] );
            if ( count( $violation ) )
            {
                $violations[$check] = $violation;
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
