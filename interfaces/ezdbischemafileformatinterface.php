<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2018
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

interface ezdbiSchemaFileFormatInterface
{
    /**
     * @param string $filename
     * @return ezdbiSchemaChecks
     */
    public function parseFile( $filename );

    public function writeFile( $filename, ezdbiSchemaChecks $schemaChecks );
}
