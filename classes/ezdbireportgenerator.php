<?php
/**
 * Formats results in various modes
 *
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiReportGenerator
{
    public static function getText( array $violations, ezdbiSchemaChecks $checks )
    {
        return "Violations:\n===========\n" . var_export( $violations, true ) .
            "\n\nChecks:\n=======\n" . var_export( $checks, true );
    }
}