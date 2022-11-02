<?php

include_once(__DIR__.'/CommandExecutingTest.php');

class CommandsTest extends CommandExecutingTest
{
    /**
     * @param string $legacyScript legacy script
     * @param array $argv used to inject arguments and options into legacy cli scripts
     * @return void
     *
     * @dataProvider provideGenericCommandsList
     */
    public function testLegacyCommands($legacyScript, $argv = array())
    {
        /// @todo in between each test, we should reset to the original $argv...
        if ($argv) {
            foreach($argv as $arg)
                $GLOBALS['argv'][] = $arg;
        } else {
            $GLOBALS['argv'][] = basename($legacyScript);
        }

        $output = $this->runCommand('ezpublish:legacy:script', array('script' => $legacyScript));
        // CLIHandler does not close its output buffering. We do it, or phpunit will mark the test as risky
        //var_dump(ob_get_level());
        //ob_implicit_flush(false);
        //$output2 = ob_get_contents();
        ob_end_clean();
    }

    public function provideGenericCommandsList()
    {
        $out = array(
            array('extension/ezdbintegrity/bin/php/checkattributes.php', array('checkattributes.php', '*')),
            array('extension/ezdbintegrity/bin/php/checkschema.php', array()),
            array('extension/ezdbintegrity/bin/php/checkstorage.php', array()),
            array('extension/ezdbintegrity/bin/php/generatedefsfrompersistentobjects.php', array()),
        );

        return $out;
    }
}
