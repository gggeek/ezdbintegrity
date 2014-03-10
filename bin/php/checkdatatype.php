<?php
/**
 * A CLI script which checks problems with a datatype in current schema
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

require 'autoload.php';

// Inject our own autoloader after the std one, as this script is supposed to be
// executable even when extension has not been activated
////spl_autoload_register( array( 'autoloadHelper', 'autoload' ) );

$cli = eZCLI::instance();

$script = eZScript::instance( array( 'description' => ( "Generate Datatype Integrity Report" ),
    'use-session' => false,
    'use-modules' => true,
    'use-extensions' => true ) );
$script->startup();
$options = $script->getOptions(
    '', //'[schemafile:][schemaformat:][database:][displaychecks][displayrows]',
    '[datatype]',
    array(
        'datatype' => 'name of the datatype to check'
        /*'schemafile' => 'Name of file with definition of db schema checks',
        'schemaformat' => 'Format of db schema checks definition file',
        'database' => 'DSN for database to connect to (default ez db)',
        'displayrows' => 'Display the offending rows, not only their count',
        'displaychecks' => 'Display the list of checks instead of executing them'*/
    )
);

$script->initialize();

if ( count( $options['arguments'] ) != 1 )
{
    $script->shutdown( 1, 'wrong argument count' );
}

$type = $options['arguments'][0];

$cli->output( "Checking datatype $type..." );

try
{
    $checker = new ezdbiDatatypeChecker();
    $checker->loadDatatypeChecks( $type );
    $violations = $checker->check( $cli );
}
catch( Exception $e )
{
    $script->shutdown( -1, $e->getMessage() );
}

$cli->output( 'Done!' );
$cli->output();

$cli->output( ezdbiReportGenerator::getText( $violations, $checker->getChecks(), $options['displaychecks'] ) );

$script->shutdown();


/*

/**
* manages autoloading for classes contained within this extension
* /
class autoloadHelper
{
    protected static $ezpClasses = null;

    public static function autoload( $className )
    {
        if ( !is_array( self::$ezpClasses ) )
        {
            self::initializeAutoload();
        }
        if ( isset( self::$ezpClasses[$className] ) )
        {
            require( self::$ezpClasses[$className] );
        }
    }

    protected static function initializeAutoload()
    {
        $autoloadOptions = new ezpAutoloadGeneratorOptions();

        $autoloadOptions->basePath = 'extension/ggsysinfo';

        $autoloadOptions->searchKernelFiles = false;
        $autoloadOptions->searchKernelOverride = false;
        $autoloadOptions->searchExtensionFiles = true;
        $autoloadOptions->searchTestFiles = false;
        $autoloadOptions->writeFiles = false;
        $autoloadOptions->displayProgress = false;

        $autoloadGenerator = new eZAutoloadGenerator( $autoloadOptions );
        // We have to jump through hoops to get eZAutoloadGenerator give us back an array
        $autoloadGenerator->setOutputCallback( array( 'autoloadHelper', 'autoloadCallback' ) );

        try
        {
            $autoloadGenerator->buildAutoloadArrays();
            $autoloadGenerator->printAutoloadArray();
        }
        catch ( Exception $e )
        {
            echo $e->getMessage() . "\n";
        }
    }

    /**
     * Used as callback for eZAutoloadGenerator
     * /
    public static function autoloadCallback( $php, $label )
    {
        // callback is called many times with info messages, only use the good one
        if ( strpos( $php, '<?php' ) !== 0 )
        {
            return;
        }
        $php = str_replace( array( '<?php', '?>', ), '', $php );
        self::$ezpClasses = eval( $php );
        // fix path to be proper relative to eZ root
        foreach ( self::$ezpClasses as $key => $val )
        {
            self::$ezpClasses[$key] = 'extension/ggsysinfo/' . $val;
        }
    }
}

*/