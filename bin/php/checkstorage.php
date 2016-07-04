<?php
/**
 * A CLI script which checks for orphan storage files
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
//require_once ( dirname( __FILE__ ) . '/../../classes/ezdbiautoloadhelper.php' );
//spl_autoload_register( array( 'ezdbiAutoloadHelper', 'autoload' ) );

$cli = eZCLI::instance();

$script = eZScript::instance( array(
    'description' => "List/delete orphan storage files (those on disk but not in db). NB: does not follow symlinks",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[cleanup][displaychecks][displayfiles]', //'[database:]',
    '',
    array(
        //'database' => 'DSN for database to connect to (default ez db)',
        'displayfiles' => 'Display the offending files, not only their count',
        'displaychecks' => 'Display the list of checks instead of executing them',
        'cleanup' => 'Do delete orphan files instead of just listing them'
    )
);
$script->initialize();

try
{
    $violations = array();
    $checker = new ezdbiStorageChecker();
    $checks = $checker->getChecks();

    if ( $options['displaychecks'])
    {
    }
    else
    {
        foreach ( array_keys( $checks ) as $check )
        {
            $cli->output( "\nNow checking $check ..." );
            $violation = $checker->check( $check, $options['cleanup'], $options['displayfiles'] );
            if ( count ( $violation ) )
            {
                $violations[$check] = $violation;
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
