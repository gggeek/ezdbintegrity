<?php

include_once(__DIR__.'/CommandExecutingTest.php');

class CommandsTest extends CommandExecutingTest
{
    /**
     * @param string $command
     * @param array $parameters
     * @return void
     *
     * @dataProvider provideGenericCommandsList
     */
    public function testGenericCommands($command, $parameters)
    {
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
            /// @todo find out a way to pass legacy params in a way that they do not get rejected by Sf arrayInput
            //array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkattributes.php')),
            /// @todo there is an error when trying to run more than one legacy script in a row, at least with eZP CP / phpunit 5...
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkschema.php')),
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/checkstorage.php')),
            array('ezpublish:legacy:script', array('script' => 'extension/ezdbintegrity/bin/php/generatedefsfrompersistentobjects.php')),
        );

        return $out;
    }
}
