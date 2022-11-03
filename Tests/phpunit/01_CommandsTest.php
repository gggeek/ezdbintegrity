<?php

include_once(__DIR__.'/CommandExecutingTest.php');

class CommandsTest extends CommandExecutingTest
{
    static $backupArgv;

    /**
     * @dataProvider provideGenericCommandsList
     */
    public function testLegacyCommands($legacyScript, $args = array())
    {
        $output = $this->runLegacyScript($legacyScript, $args);
    }

    public function provideGenericCommandsList()
    {
        $out = array(
            array('extension/ezdbintegrity/bin/php/checkattributes.php', array('*')),
            array('extension/ezdbintegrity/bin/php/checkattributes.php', array('--displaychecks')),
            array('extension/ezdbintegrity/bin/php/checkschema.php', array()),
            array('extension/ezdbintegrity/bin/php/checkschema.php', array('--displaychecks')),
            array('extension/ezdbintegrity/bin/php/checkstorage.php', array()),
            array('extension/ezdbintegrity/bin/php/checkstorage.php', array('--displaychecks')),
            array('extension/ezdbintegrity/bin/php/generatedefsfrompersistentobjects.php', array(sys_get_temp_dir() . '/generated_checks.php')),
        );

        return $out;
    }
}
