<?php
/**
 * Created by PhpStorm.
 * User: gaetano.giunta
 * Date: 10/03/14
 * Time: 17.02
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