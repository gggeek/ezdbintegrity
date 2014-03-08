<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Structure holding the definition of (a set of) schema checks
 */
class ezdbiSchemaChecks
{
    protected $FK = array();

    public function getForeignKeys()
    {
        return $this->FK;
    }

    public function addForeignKey( $childTable, $childCol, $parentTable, $parentCol, $filter=null, $name='' )
    {
        if ( $name != '' )
        {
            $this->FK[$name] = array(
                'childTable'=> $childTable,
                'childCol' => $childCol,
                'parentTable' => $parentTable,
                'parentCol' => $parentCol,
                'exceptions' => $filter
            );
        }
        else
        {
            $this->FK[] = array(
                'childTable'=> $childTable,
                'childCol' => $childCol,
                'parentTable' => $parentTable,
                'parentCol' => $parentCol,
                'exceptions' => $filter
            );
        }
    }

    /**
     * Adds checks from another set into this one
     *
     * @param ezdbiSchemaChecks $checks
     */
    public function merge( ezdbiSchemaChecks $checks )
    {
        $this->FK = array_merge( $this->FK, $checks->FK );
    }
}