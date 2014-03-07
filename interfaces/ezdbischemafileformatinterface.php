<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

interface ezdbiSchemaFileFormatInterface
{
    public function parseFile( $filename );

    public function writeFile( $filename, ezdbiSchemaChecks $schemaChecks );
}