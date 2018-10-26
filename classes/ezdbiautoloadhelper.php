<?php

/**
 * A custom autoloader, provided in case this extension is not properly activated in eZ4
 */
class ezdbiAutoloadHelper
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

        $autoloadOptions->basePath = 'extension/ezdbintegrity';

        $autoloadOptions->searchKernelFiles = false;
        $autoloadOptions->searchKernelOverride = false;
        $autoloadOptions->searchExtensionFiles = true;
        $autoloadOptions->searchTestFiles = false;
        $autoloadOptions->writeFiles = false;
        $autoloadOptions->displayProgress = false;

        $autoloadGenerator = new eZAutoloadGenerator( $autoloadOptions );
        // We have to jump through hoops to get eZAutoloadGenerator give us back an array
        $autoloadGenerator->setOutputCallback( array( 'ezdbiAutoloadHelper', 'autoloadCallback' ) );

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
     */
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
            self::$ezpClasses[$key] = 'extension/ezdbintegrity/' . $val;
        }
    }
}
