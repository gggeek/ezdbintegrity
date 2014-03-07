<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Structure holding the definition of schema checks
 */
class ezdbiSchemaChecks
{
    protected $FK = array();

    public function getForeignKeys()
    {
        return $this->FK;
    }

    public function addForeignKey( $childTable, $childCol, $parentTable, $parentCol, $name='' )
    {
        if ( $name != '' )
        {
            $this->FK[$name] = array(
                'childTable'=> $childTable,
                'childCol' => $childCol,
                'parentTable' => $parentTable,
                'parentCol' => $parentCol,
            );
        }
        else
        {
            $this->FK[] = array(
                'childTable'=> $childTable,
                'childCol' => $childCol,
                'parentTable' => $parentTable,
                'parentCol' => $parentCol,
            );
        }
    }
}