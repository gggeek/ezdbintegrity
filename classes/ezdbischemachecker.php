<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiSchemaChecker
{
    protected $db;
    protected $checks;

    public function __construct( $dsn='' )
    {
        if ( $dsn == '' )
        {
            $db = eZDB::instance();
        }
        else
        {
            throw new Exception( "Custom db connection unsupported for now" );
        }
        $this->db = $db;
    }

    public function loadSchemaFile( $fileName, $fileFormat )
    {
        switch ( $fileFormat )
        {
            case 'ezini':
                $parser = new ezdbiIniFormat();
                $this->checks = $parser->parseFile( $fileName );
                break;

            default:
                throw new Exception( "Schema file format $fileFormat not supported" );
        }
    }

    /**
     * @return array
     */
    public function checkSchema( $returnData=true )
    {
        $violations = array(
            'FK' => array()
        );

        foreach( $this->checks->getForeignKeys() as $def )
        {
            // check that both tables exist
            if ( !$this->tableExists( $def['childTable'] ) && $this->tableExists( $def['parentTable'] ) )
            {
                continue;
            }

            $violatingRows = $this->countFKViolations( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'] );
            if ( $violatingRows > 0 )
            {
                $def['violatingRowCount'] = $violatingRows;
                if ( $returnData )
                {
                    $def['violatingRows'] = $this->getFKViolations( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'] );
                }
                $violations['FK'][] = $def;
            }
        }

        return $violations;
    }

    public function countFKViolations( $childTable, $childCol, $parentTable, $parentCol )
    {
        $sql =
            "SELECT COUNT(*) AS violations " .
            "FROM " . $this->escapeIdentifier( $childTable ) . " " .
            "WHERE " .  $this->escapeIdentifier( $childCol ) . " NOT IN ( " .
                "SELECT DISTINCT " . $this->escapeIdentifier( $parentCol ) . " " .
                "FROM " . $this->escapeIdentifier( $parentTable ) . " )";
        $results = $this->db->arrayQuery( $sql );
        return $results[0]['violations'];
    }

    public function getFKViolations( $childTable, $childCol, $parentTable, $parentCol )
    {
        $sql =
            "SELECT * " .
            "FROM " . $this->escapeIdentifier( $childTable ) . " " .
            "WHERE " .  $this->escapeIdentifier( $childCol ) . " NOT IN ( " .
                "SELECT DISTINCT " . $this->escapeIdentifier( $parentCol ) . " " .
                "FROM " . $this->escapeIdentifier( $parentTable ) . " )";
        return $this->db->arrayQuery( $sql );
    }

    public function getChecks()
    {
        return $this->checks;
    }

    /// @todo
    protected function escapeIdentifier( $name )
    {
        return $name;
    }

    /// @todo
    protected function tableExists( $table )
    {
        return true;
    }
}