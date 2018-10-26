<?php
/**
 * Formats results in various modes
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2018
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiReportGenerator
{
    public static function getText( array $violations, $checks, $displayChecks=false )
    {
        if ( $displayChecks )
        {
            return "Checks:\n=======\n" . var_export( $checks, true );
        }
        else
        {
            return "Violations:\n===========\n" . var_export( $violations, true );
        }
    }
}
