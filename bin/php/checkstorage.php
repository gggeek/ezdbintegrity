<?php
/**
 * A CLI script which checks for orphan storage files
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2016-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
//require_once ( dirname( __FILE__ ) . '/../../classes/ezdbiautoloadhelper.php' );
//spl_autoload_register( array( 'ezdbiAutoloadHelper', 'autoload' ) );


if ( !function_exists( 'onStopSignalCST' ) )
{
    function onStopSignalCST( $sigNo )
    {
        global $scriptState;

        $violations = $scriptState['violations'];
        $cli = $scriptState['cli'];
        $checks = $scriptState['checks'];
        $options = $scriptState['options'];
        $script = $scriptState['script'];

        $cli->output( ezdbiReportGenerator::getText( $violations, $checks, $options['displaychecks'] ) );

        $script->shutdown();
        die();
    }

    // We can not just use $GLOBALS as sometimes the script is run within a class (in eZ5), sometimes not...
    function saveStateCST( $stateArray )
    {
        global $scriptState;

        $scriptState = $stateArray;
    }
}

$cli = eZCLI::instance();

$script = eZScript::instance( array(
    'description' => "List/delete orphan storage files (those on disk but not in db). NB: does not follow symlinks",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[cleanup][displaychecks][displayfiles][omitcheck:]', //'[database:]',
    '',
    array(
        //'database' => 'DSN for database to connect to (default ez db)',
        'displayfiles' => 'Display the offending files, not only their count',
        'displaychecks' => 'Display the list of checks instead of executing them',
        'cleanup' => 'Do delete orphan files instead of just listing them',
        'omitcheck' => 'Omit specific checks. Use `displaychecks` to list all check names. Can be multiple, comma separated',
    )
);
$script->initialize();

if ( $options['omitcheck'] != '' )
{
    $options['omitcheck'] = explode(',', $options['omitcheck']);
}

try
{
    $violations = array();
    $checker = new ezdbiStorageChecker();
    $checks = $checker->getChecks();

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

    if ( $options['displaychecks'])
    {
        // all already done
    }
    else
    {
        if ( function_exists( 'pcntl_signal' ) )
        {
            pcntl_signal(SIGTERM, 'onStopSignalCST');
            pcntl_signal(SIGINT, 'onStopSignalCST');
            saveStateCST( array(
                'cli' => $cli,
                'script' => $script,
                'checks' => $checks,
                'violations' => &$violations,
                'options' => $options
            ) );
        }

        foreach ( array_keys( $checks ) as $check )
        {
            $cli->output( "\nNow checking $check ..." );
            $violation = $checker->check( $check, $options['cleanup'], $options['displayfiles'] );
            if ( count ( $violation ) )
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
    }

    $cli->output( ezdbiReportGenerator::getText( $violations, $checks, $options['displaychecks'] ) );

    $script->shutdown();
}
catch( Exception $e )
{
    $cli->error( $e->getMessage() );
    $script->shutdown( -1 );
}
