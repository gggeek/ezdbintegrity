<?php

include_once(__DIR__.'/CommandExecutingTest.php');

class CommandsTest extends CommandExecutingTest
{
    static $backupArgv;

    /**
     * @param string $legacyScript legacy script
     * @param array $args used to inject arguments and options into legacy cli scripts
     * @return void
     *
     * @dataProvider provideGenericCommandsList
     *
     * @todo with php 7.4, we (sometimes) get Uncaught UnexpectedValueException: RecursiveDirectoryIterator::__construct in ezdbintegrity/vendor/phpunit/php-file-iterator/src/Facade.php on line 36
     */
    public function testLegacyCommands($legacyScript, $args = array())
    {
        if (!is_array(self::$backupArgv)) {
            self::$backupArgv = $GLOBALS['argv'];
        }

        // This is required to pass in legacy-script arguments
        $GLOBALS['argv'] = array_merge(self::$backupArgv, array($legacyScript), $args);
        $_SERVER['argv'] = $GLOBALS['argv'];

        // There is a bug in LegacyEmbedScriptCommand when running many legacy scripts in a row. We hack around it
        $container = $this->getContainer();
        // first, we make sure not to try to instantiate the web-kernel-handler
        $ch = $this->getContainer()->get('ezpublish_legacy.kernel_handler.cli');
        $container->set( 'ezpublish_legacy.kernel.lazy', null );
        $container->set( 'ezpublish_legacy.kernel_handler', $ch );
        $container->set( 'ezpublish_legacy.kernel_handler.web', $ch );
        // then we get the kernel
        /** @var callable $k */
        $k = $container->get('ezpublish_legacy.kernel');
        $k = $k();
        // and patch *its own* cli handler
        /** @var \eZ\Publish\Core\MVC\Legacy\Kernel\CLIHandler $cl */
        $ch = \Closure::bind(function () { return $this->kernelHandler; }, $k, 'ezpKernel');
        $ch = $ch();
        $ch->setEmbeddedScriptPath($legacyScript);

        /// @todo try wrapping this in one more ob_start
        $output = $this->runCommand('ezpublish:legacy:script', array('script' => $legacyScript));
        // CLIHandler does not close its output buffering. We do it, or phpunit will mark the test as risky
        ob_end_clean();
    }

    public function provideGenericCommandsList()
    {
        $out = array(
            array('extension/ezdbintegrity/bin/php/checkattributes.php', array('*')),
            array('extension/ezdbintegrity/bin/php/checkattributes.php', array('--displaychecks')),
            array('extension/ezdbintegrity/bin/php/checkschema.php', array()),
            array('extension/ezdbintegrity/bin/php/checkstorage.php', array()),
            array('extension/ezdbintegrity/bin/php/generatedefsfrompersistentobjects.php', array()),
        );

        return $out;
    }
}
