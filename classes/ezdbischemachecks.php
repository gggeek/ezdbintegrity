<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2020
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Structure holding the definition of (a set of) schema checks
 */
class ezdbiSchemaChecks
{
    protected $FK = array();
    protected $queries = array();

    public function getForeignKeys()
    {
        return $this->FK;
    }

    public function getQueries()
    {
        return $this->queries;
    }

    public function getForeignKey( $name )
    {
        if ( ! isset( $this->FK[$name] ) )
            throw new \Exception( "No FK check '$name' defined" );
        return $this->FK[$name];
    }

    public function getQuery( $name )
    {
        if ( ! isset( $this->FK[$name] ) )
            throw new \Exception( "No query check '$name' defined" );
        return $this->queries[$name];
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

    public function addQuery( $sql, $description, $longDesc='' /*, $expectedRows = 0*/ )
    {
        $desc = array(
            'sql'=> $sql,
            'description' => $description,
            //'expectedRows' => $expectedRows
        );
        if ( $longDesc != 'longDesc' )
        {
            $data[''] = $longDesc;
        }
        $this->queries[] = $desc;
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
