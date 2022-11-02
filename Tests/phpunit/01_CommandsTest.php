<?php

include_once(__DIR__.'/CommandExecutingTest.php');

class CommandsTest extends CommandExecutingTest
{
    /**
     * @param string $command
     * @param array $parameters
     * @param array $argv used to inject arguments and options into legacy cli scripts
     * @return void
     *
     * @dataProvider provideGenericCommandsList
     */
    public function testGenericCommands($command, $parameters, $argv = array())
    {
        /// @todo in between each test, we should reset to the original $argv...
        if ($argv) {
            foreach($argv as $arg)
                $GLOBALS['argv'][] = $arg;
        }
        $output = $this->runCommand($command, $parameters);
        // CLIHandler does not close its output buffering. We do it, or phpunit will mark the test as risky
        //var_dump(ob_get_level());
        //ob_implicit_flush(false);
        //$output2 = ob_get_contents();
        ob_end_clean();
    }

    public function provideGenericCommandsList()
    {
        $out = array(
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkattributes.php'), array('checkattributes.php', '*')),
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkschema.php'), array()),
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkstorage.php'), array()),
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/generatedefsfrompersistentobjects.php'), array()),
        );

        return $out;
    }
}
