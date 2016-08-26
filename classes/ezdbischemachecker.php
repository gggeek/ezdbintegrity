<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2016
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiSchemaChecker extends ezdbiBaseChecker
{
    /** @var eZDBInterface $db */
    protected $db;
    /** @var eZDBSchemaInterface $schema */
    protected $schema;
    /** @var ezdbiSchemaChecks $checks */
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

        /// @todo for mysql, execute "SET sql_mode='PIPES_AS_CONCAT'" for sql compatibility
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
     * @param string $check
     * @param bool $returnData
     * @param bool $omitDefinitions
     * @return array
     * @throws Exception
     */
    public function check( $check, $returnData=false, $omitDefinitions=false )
    {
        $checkType = explode ('_', $check, 2 );
        $type = $checkType[0];
        switch ( $type )
        {
            case 'FK':
                return $this->checkForeignKey( $checkType[1], $returnData, $omitDefinitions );
            case 'Other':
                return $this->checkQuery( $checkType[1], $returnData );
            default:
                throw new \Exception("Check type unknown: '$checkType'" );
        }
    }

    /**
     * Executes all known schema checks, return an array w. violations.
     * NB: this can take a while to execute...
     *
     * @param bool $returnData
     * @param bool $omitDefinitions
     * @return array
     */
    public function checkSchema( $returnData=false, $omitDefinitions=false )
    {
        $violations = array();

        foreach( $this->getChecksNames() as $check )
        {
            $violation = $this->check( $check, $omitDefinitions=false );
            if ( count( $violation ) )
            {
                $violations[$check] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @param string $check
     * @param bool $returnData
     * @return array empty if no violations
     */
    protected function checkForeignKey( $check, $returnData=false, $omitDefinitions=false )
    {
        $def = $this->checks->getForeignKey( $check );

        if ( !$this->tableExists( $def['childTable'] ) || !$this->tableExists( $def['parentTable'] ) )
        {
            return array();
        }

        $violations = array();

        if ( $omitDefinitions )
        {
            $violatingDef = false;
        }
        else
        {
            $violatingDef = $this->checkFKDefinition( $def['childTable'], $def['childCol'], $def['parentTable'], $def['parentCol'] );
        }
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
            $violations[] = $def;
        }
        return $violations;
    }

    /**
     * @param string $check
     * @param bool $returnData
     * @return array empty if no violations
     */
    protected function checkQuery( $check, $returnData=false )
    {
        $violations = array();

        $def = $this->checks->getQuery( $check );

        $violatingRows = $this->countCustomQuery( $def['sql'] );

        if ( $violatingRows )
        {
            $def['violatingRowCount'] = $violatingRows;
            if ( $returnData && $violatingRows )
            {
                $def['violatingRows'] = $this->checkCustomQuery( $def['sql'] );
            }
            $violations[] = $def;
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

    public function countCustomQuery( $sql )
    {
        $sql = 'SELECT COUNT(*) AS rows FROM ( ' . $sql . ') AS subquery';
        $results = $this->db->arrayQuery( $sql );
        return $results[0]['rows'];
    }

    public function checkCustomQuery( $sql )
    {
        return $this->db->arrayQuery( $sql );
    }

    protected function getChecksNames()
    {
        return array_keys( $this->getChecks() );
    }

    /**
     * Returns the list of checks
     *
     * @return array name => description
     */
    public function getChecks()
    {
        $out = array();
        foreach( $this->checks->getForeignKeys() as $key => $value )
        {
            $out['FK_' . $key] = $value;
        }
        foreach( $this->checks->getQueries() as $key => $value )
        {
            $out['Other_' . $key] = $value;
        }
        return $out;
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