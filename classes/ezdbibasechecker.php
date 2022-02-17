<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2016-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiBaseChecker
{
    protected $cli;

    public function setCli( eZCLI $cli )
    {
        $this->cli = $cli;
    }

    protected function output( $msg, $addEOL = true )
    {
        if( $this->cli )
        {
            $this->cli->output( $msg, $addEOL );
        }
    }
}
