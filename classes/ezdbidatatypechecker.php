<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiDatatypeChecker extends ezdbiBaseChecker
{
    protected $checkerClasses = array();
    protected $limit = 100;

    public function loadDatatypeChecks()
    {
        // Find if datatype is registered
        $ini = eZINI::instance( 'content.ini' );
        foreach( $ini->variable( 'DataTypeSettings', 'AvailableDataTypes' ) as $type )
        {
            $this->loadDatatypeChecksforType( $type );
        }
    }

    /**
     * @param string $type datatype name
     * @return array|false
     * @throws Exception
     */
    public function loadDatatypeChecksforType( $type )
    {
        // Find if datatype is registered
        $ini = eZINI::instance( 'content.ini' );
        if ( !in_array( $type, $ini->variable( 'DataTypeSettings', 'AvailableDataTypes' ) ) )
        {
            throw new Exception( "Datatype '$type' does not seem to be registered" );
        }

        // Find if we have registered type checkers for it
        $checker = 'DataTypeChecker_' . $type;
        $ini = eZINI::instance( 'ezdbintegrity.ini' );
        if ( !$ini->hasVariable( 'DataTypeSettings', $checker ) )
        {
            //throw new Exception( "No datatype checker '$checker' seem to be registered in ezdbintegrity.ini" );
            return false;
        }
        $checkerClasses = $ini->variable( 'DataTypeSettings', $checker );
        if ( !is_array( $checkerClasses ) )
        {
            $checkerClasses = array( $checkerClasses );
        }
        foreach( $checkerClasses as $checkerClass )
        {
            /// @todo check interface
            if( !class_exists( $checkerClass ) )
            {
                throw new Exception( "Datatype checker class '$checkerClass' does not exist" );
            }
        }

        $this->checkerClasses[$type] = $checkerClasses;
        return $checkerClasses;
    }

    public function getChecks()
    {
        return $this->checkerClasses;
    }

    /**
     * Checks for integrity all object attributes of a given datatype.
     * The rules applied for each datatype depend on ini settings.
     * The datatype to check is set via loadDatatypeChecks()
     *
     * @param bool $also_unpublished
     * @return array
     * @throws Exception
     */
    public function check( $type, $also_unpublished = false )
    {
        if ( !isset( $this->checkerClasses[$type] ) )
        {
            $this->loadDatatypeChecksforType( $type );
        }

        $warnings = array();

        // Loop over all class attributes using the datatype:
        $classAttributes = eZContentClassAttribute::fetchList( true, array(
            'version' => eZContentClass::VERSION_STATUS_DEFINED,
            'data_type' => $type
        ) );

        $db = eZDB::instance();
        foreach( $classAttributes as $classAttribute )
        {
            $this->output( "Checking attribute '" . $classAttribute->attribute( 'identifier' ) . "' in class " . $classAttribute->attribute( 'contentclass_id' ) . '...' );

            $checkers = array();
            foreach( $this->checkerClasses[$type] as $key => $checkerClass )
            {
                $checkers[$key] = call_user_func_array( array( $checkerClass, 'instance' ), array( $classAttribute ) );
            }
            $offset = 0;
            $total = 0;
            do
            {
                $tables = 'ezcontentobject_attribute';
                $where = 'contentclassattribute_id = ' . $classAttribute->attribute( 'id' );
                if ( !$also_unpublished )
                {
                    $tables .= ', ezcontentobject';
                    $where .= ' AND ezcontentobject.id = ezcontentobject_attribute.contentobject_id' .
                        ' AND ezcontentobject.current_version = ezcontentobject_attribute.version' .
                        ' AND ezcontentobject.status = 1';
                }
                $query = "SELECT ezcontentobject_attribute.* ".
                    "FROM $tables " .
                    "WHERE $where";
                $rows = $db->arrayQuery( $query, array( 'offset' => $offset, 'limit' => $this->limit ) );
                if ( $rows === false )
                {
                    throw new Exception( "DB Error, something is deeply wrong here" );
                }
                foreach( $rows as $row )
                {
                    foreach( $checkers as $checker )
                    {
                        $problems = $checker->checkObjectAttribute( $row );
                        if ( count( $problems ) )
                        {
                            $warnings = array_merge( $warnings, $problems );
                        }
                    }
                }
                $offset += $this->limit;
                $total += count( $rows );

            } while ( count( $rows ) > 0 );

            $this->output( "Checked $total object attributes" );
        }

        return $warnings;
    }
}