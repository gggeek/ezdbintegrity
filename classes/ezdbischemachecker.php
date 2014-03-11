<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiSchemaChecker extends ezdbiBaseChecker
{
    /// @var eZDBInterface $db
    protected $db;
    /// @var eZDBSchemaInterface $schema
    protected $schema;

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
        $this->schema = eZDbSchema::instance( $db )->schema();
    }

    /**
     * This loads the file with the schema checks, not the schema definition per se
     *
     * @param string $fileName
     * @param string $fileFormat
     * @throws Exception
     */
    public function loadChecksFile( $fileName, $fileFormat )
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
     * Executes all known schema checks, return an array w. violations
     * @param bool $returnData
     * @return array
     */
    public function checkSchema( $returnData=false )
    {
        $violations = array(
            'FK' => array()
        );

        foreach( $this->checks->getForeignKeys() as $def )
        {
            // check that both tables exist
            if ( !$this->tableExists( $def['childTable'] ) || !$this->tableExists( $def['parentTable'] ) )
            {
                continue;
            }

            $violatingDef = $this->checkFKDefinition( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'] );
            $violatingRows = $this->countFKViolations( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'], $def['exceptions'] );

            if ( $violatingDef || $violatingRows )
            {
                if ( $violatingDef )
                    $def['definitionMismatch'] = $violatingDef;
                $def['violatingRowCount'] = $violatingRows;
                if ( $returnData && $violatingRows )
                {
                    $def['violatingRows'] = $this->getFKViolations( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'], $def['exceptions'] );
                }
                $violations['FK'][] = $def;
            }
        }

        return $violations;
    }

    public function checkFKDefinition( $childTable, $childCol, $parentTable, $parentCol )
    {
        $diffs = null;
        if ( !isset( $this->schema[$childTable]['fields'][$childCol] ) )
        {
            $diffs[] = "Column type mismatch: $childTable.$childCol missing";
        }
        if ( !isset( $this->schema[$parentTable]['fields'][$parentCol] ) )
        {
            $diffs[] = "Column type mismatch: $parentTable.$parentCol missing";
        }
        if ( count( $diffs ) )
            return $diffs;

        $t1 = $this->normalizeColDef( $this->schema[$childTable]['fields'][$childCol] );
        $t2 = $this->normalizeColDef( $this->schema[$parentTable]['fields'][$parentCol] );

        if ( $t1['type'] != $t2['type'] )
        {
            $diffs[] = "Column type mismatch: {$t1['type']} vs. {$t2['type']}";
        }
        else
        {
            if ( $t1['length'] != $t2['length'] )
            {
                $diffs[] = "Column length mismatch: {$t1['length']} vs. {$t2['length']}";
            }
        }
        return $diffs;
    }

    /**
     * @todo check if this works on oracle and postgres
     * @param array $def
     * @return array
     */
    protected function normalizeColDef( array $def )
    {
        switch( $def['type'] )
        {
            case 'auto_increment':
                $def['type'] = 'int';
                $def['length'] = 11;
                break;
            default:
        }
        return $def;
    }

    public function countFKViolations( $childTable, $childCol, $parentTable, $parentCol, $exceptions = null )
    {
        $sql =
            "SELECT COUNT(*) AS violations " .
            "FROM " . $this->escapeIdentifier( $childTable ) . " " .
            "WHERE " .  $this->escapeIdentifier( $childCol ) . " NOT IN ( " .
                "SELECT DISTINCT " . $this->escapeIdentifier( $parentCol ) . " " .
                "FROM " . $this->escapeIdentifier( $parentTable ) . " )";
        if( $exceptions != null )
        {
            $sql .= ' AND ' . $exceptions;
        }
        $results = $this->db->arrayQuery( $sql );
        return $results[0]['violations'];
    }

    public function getFKViolations( $childTable, $childCol, $parentTable, $parentCol, $exceptions = null )
    {
        $sql =
            "SELECT * " .
            "FROM " . $this->escapeIdentifier( $childTable ) . " " .
            "WHERE " .  $this->escapeIdentifier( $childCol ) . " NOT IN ( " .
                "SELECT DISTINCT " . $this->escapeIdentifier( $parentCol ) . " " .
                "FROM " . $this->escapeIdentifier( $parentTable ) . " )";
        if( $exceptions != null )
        {
            $sql .= ' AND ' . $exceptions;
        }
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

    protected function tableExists( $table )
    {
        return array_key_exists( $table, $this->schema );
    }
}