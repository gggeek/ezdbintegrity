<?php

use eZ\Bundle\EzPublishCoreBundle\Console\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;

abstract class CommandExecutingTestBase extends KernelTestCase
{
    protected $leftovers = array();

    /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
    private $_container;
    /** @var \eZ\Bundle\EzPublishCoreBundle\Console\Application $app */
    protected $app;
    /** @var StreamOutput $output */
    protected $output;

    // tell to phpunit not to mess with ezpublish legacy global vars...
    protected $backupGlobalsBlacklist = array('eZCurrentAccess');

    protected static $backupArgv;

    protected function doSetUp()
    {
        $this->_container = $this->bootContainer();

        $this->app = new Application(static::$kernel);
        $this->app->setAutoExit(false);
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);
        $this->leftovers = array();
    }

    /**
     * Fetches the data from the output buffer, resetting it.
     * It would be nice to use BufferedOutput, but that is not available in Sf 2.3...
     * @return null|string
     */
    protected function fetchOutput()
    {
        if (!$this->output) {
            return null;
        }

        $fp = $this->output->getStream();
        rewind($fp);
        $out = stream_get_contents($fp);

        fclose($fp);
        $fp = fopen('php://temp', 'r+');
        $this->output = new StreamOutput($fp);

        return $out;
    }

    protected function doTearDown()
    {
        foreach ($this->leftovers as $file) {
            unlink($file);
        }

        // clean buffer, just in case...
        if ($this->output) {
            $fp = $this->output->getStream();
            fclose($fp);
            $this->output = null;
        }

        // shuts down the kernel etc...
        // Since we added the BC trick with doSetup/doTeardown, this would be a loop. So we do all here
        //parent::tearDown();
        static::ensureKernelShutdown();
        static::$kernel = null;
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     * @throws Exception
     */
    protected function bootContainer()
    {
        static::ensureKernelShutdown();

        if (!isset($_SERVER['SYMFONY_ENV'])) {
            throw new \Exception("Please define the environment variable SYMFONY_ENV to specify the environment to use for the tests");
        }
        // Run in our own test environment. Sf by default uses the 'test' one. We let phpunit.xml set it...
        // We also allow to disable debug mode
        $options = array(
            'environment' => $_SERVER['SYMFONY_ENV']
        );
        if (isset($_SERVER['SYMFONY_DEBUG'])) {
            $options['debug'] = $_SERVER['SYMFONY_DEBUG'];
        }
        try {
            static::bootKernel($options);
        } catch (\RuntimeException $e) {
            throw new \RuntimeException($e->getMessage() . " Did you forget to define the environment variable KERNEL_DIR?", $e->getCode(), $e->getPrevious());
        }

        // In Sf4 we do have the container available, in Sf3 we do not
        return isset(static::$container) ? static::$container : static::$kernel->getContainer();
    }

    protected function getContainer()
    {
        return $this->_container;
    }

    /**
     * @param string $commandName
     * @param array $params
     * @param bool $checkExitCode
     * @return string|null
     * @throws Exception
     */
    protected function runCommand($commandName, array $params = array(), $checkExitCode = true)
    {
        $exitCode = $this->app->run($this->buildInput($commandName, $params), $this->output);
        $output = $this->fetchOutput();
        if ($checkExitCode) {
            $this->assertSame(0, $exitCode, 'CLI Command failed. Output: ' . $output);
        }
        return $output;
    }

    /**
     * @param string $legacyScript legacy script
     * @param array $args used to inject arguments and options into legacy cli scripts
     * @return string
     */
    public function runLegacyScript($legacyScript, $args = array())
    {
        // There is a bug in LegacyEmbedScriptCommand when running many legacy scripts in a row. We hack around it

        $container = $this->getContainer();

        if (!is_array(self::$backupArgv)) {
            self::$backupArgv = $GLOBALS['argv'];

            // first, we make sure not to try to instantiate the web-kernel-handler
            $ch = $this->getContainer()->get('ezpublish_legacy.kernel_handler.cli');
            $container->set( 'ezpublish_legacy.kernel.lazy', null );
            $container->set( 'ezpublish_legacy.kernel_handler', $ch );
            $container->set( 'ezpublish_legacy.kernel_handler.web', $ch );
        }

        // then we get the kernel
        /** @var callable $k */
        $k = $container->get('ezpublish_legacy.kernel');
        $k = $k();
        // and patch *its own* cli handler
        /** @var \eZ\Publish\Core\MVC\Legacy\Kernel\CLIHandler $cl */
        $ch = \Closure::bind(function () { return $this->kernelHandler; }, $k, 'ezpKernel');
        $ch = $ch();
        $ch->setEmbeddedScriptPath($legacyScript);

        // This is required to pass in legacy-script arguments
        $GLOBALS['argv'] = array_merge(self::$backupArgv, array($legacyScript), $args);
        $_SERVER['argv'] = $GLOBALS['argv'];

        // CLIHandler does not close its output buffering. We do it, or phpunit will mark the test as risky.
        // We also do our own buffering to avoid polluting test output
        ob_start();
        $this->runCommand('ezpublish:legacy:script', array('script' => $legacyScript));
        ob_get_contents();
        ob_end_clean();
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * @param $commandName
     * @param array $params
     * @return ArrayInput
     */
    protected function buildInput($commandName, array $params = array())
    {
        $params = array_merge(['command' => $commandName], $params);
        return new ArrayInput($params);
    }
}

// Auto-adapt to PHPUnit 8 that added a `void` return-type to the setUp/tearDown methods
/// @todo check: can we leave this to the parent class from Symfony?
if (method_exists(\ReflectionMethod::class, 'hasReturnType') && (new \ReflectionMethod(TestCase::class, 'tearDown'))->hasReturnType()) {
    // eval is required for php 5.6 compatibility
    eval('abstract class CommandExecutingTest extends CommandExecutingTestBase
    {
        protected function setUp(): void
        {
            $this->doSetUp();
        }

        protected function tearDown(): void
        {
            $this->doTearDown();
        }
    }');
}else {
    abstract class CommandExecutingTest extends CommandExecutingTestBase
    {
        protected function setUp()
        {
            $this->doSetUp();
        }

        protected function tearDown()
        {
            $this->doTearDown();
        }
    }
}
