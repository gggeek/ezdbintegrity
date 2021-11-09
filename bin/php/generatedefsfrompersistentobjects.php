<?php
/**
 * A CLI script which checks all persistent object defs for FKs and generates a file with definitions
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2021
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
////spl_autoload_register( array( 'autoloadHelper', 'autoload' ) );

$cli = eZCLI::instance();

$script = eZScript::instance( array(
    'description' => "Generate FK definition from eZPersistentObjects",
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '[schemaformat:][extensions]',
    '[schemafile]',
    array(
        'schemafile' => 'Name of file with db schema checks to create',
        'schemaformat' => 'Format of db schema checks definition file (ini by default)',
        'extensions' => 'Also parse classes from extensions'
    )
);

$script->initialize();

$parser = new ezdbiPersistentObjectFormat();
$checks = new ezdbiSchemaChecks();

$cli->output( 'Checking classes from kernel autoloads...' );
$classes = include( 'autoload/ezp_kernel.php' );
ksort( $classes );
foreach( $classes as $class => $file )
{
    if( is_subclass_of( $class, 'eZPersistentObject' ) )
    {
        $classChecks = $parser->parseFile( $class );
        $cli->output( "Class: $class, found " . count( $classChecks->getForeignKeys() ) . " keys" );
        $checks->merge( $classChecks );
    }
}

if ( $options['extensions'] )
{
    $cli->output( 'Checking classes from extension autoloads...' );
    $classes = include( 'var/autoload/ezp_extension.php' );
    ksort( $classes );
    foreach( $classes as $class => $file )
    {
        if( is_subclass_of( $class, 'eZPersistentObject' ) )
        {
            $classChecks = $parser->parseFile( $class );
            $cli->output( "Class: $class, found " . count( $classChecks->getForeignKeys() ) . " keys" );
            $checks->merge( $classChecks );
        }
    }
}

if ( !count( $options['arguments'] ) )
{
    $options['arguments'] = array( 'php://stdout' );
}

switch( $options['schemaformat'] )
{
    case 'ezini':
    case '':
        $parser = new ezdbiIniFormat();
        $parser->writeFile( $options['arguments'][0], $checks );
        break;
    default:
        $cli->error( "Unsupported output format: {$options['schemaformat']}" );
}

$cli->output( 'Done!' );
$cli->output();

$script->shutdown();
