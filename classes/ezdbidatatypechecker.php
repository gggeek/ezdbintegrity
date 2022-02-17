<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * This class checks existing content attributes for integrity.
 * Depending on the datatype of each attribute, a specific actual checker is used; atual checkers need to implement
 * ezdbiDatatypeCheckerInterface.
 * Code is a bit dirty because we offer an ultra-flexible api to make CLI users happy.
 * It also avoids loading ezcontentobjects not to incur into cache inflation, and tries to be as fast as possible
 * while still using most of the eZ4 APIs and eschewing direct db access.s
 */
class ezdbiDatatypeChecker extends ezdbiBaseChecker
{
    /// 2-level array of names of php classes implementing actual checks. 1st level key is datatype
    protected $checkerClasses = array();
    // used for batching when fetching attributes to avoid running ot of memory
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
     * @param string $type datatype name or classidentifier/attributeidentifier
     * @return array|false
     * @throws Exception
     */
    public function loadDatatypeChecksforType( $type )
    {
        if ( $this->isAttributeDefinition( $type ) )
        {
            $type = $this->datatypeByAttributeDefinition( $type );
        }

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
            if( !class_exists( $checkerClass ) || !in_array( 'ezdbiDatatypeCheckerInterface', class_implements( $checkerClass ) ) )
            {
                throw new Exception( "Datatype checker class '$checkerClass' does not exist or does not have good interface" );
            }
        }

        $this->checkerClasses[$type] = $checkerClasses;
        return $checkerClasses;
    }

    /**
     * @return array
     */
    public function getChecks()
    {
        $checks = $this->checkerClasses;
        ksort( $checks );
        return $checks;
    }

    /**
     * Checks for integrity all object attributes of a given datatype. Or for a single attribute of one class.
     * The rules applied for each datatype depend on ini settings.
     * The datatype to check is set via loadDatatypeChecks()
     *
     * @param string $type datatype name or classidentifier/attributeidentifier
     * @param bool $also_unpublished
     * @return array
     * @throws Exception
     */
    public function check( $type, $also_unpublished = false )
    {
        $classIdentifierFilter = null;
        $attributeIdentifierFilter = null;
        if ( $this->isAttributeDefinition( $type ) )
        {
            list( $classIdentifierFilter, $attributeIdentifierFilter ) = $this->parseAttributeDefinition( $type );
            $type = $this->datatypeByAttributeDefinition( $type );
        }

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
            $classIdentifier = eZContentClass::classIdentifierByID( $classAttribute->attribute( 'contentclass_id' ) );
            $attributeIdentifier = $classAttribute->attribute( 'identifier' );

            if ( ( $classIdentifierFilter !== null && $classIdentifier !== $classIdentifierFilter )
                || ( $attributeIdentifierFilter !== null && $attributeIdentifier !== $attributeIdentifierFilter ) )
            {
                continue;
            }

            $this->output( "Checking attribute '$attributeIdentifier' in class '$classIdentifier'..." );

            // the checkers get initialized once per class attribute
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

    protected function isAttributeDefinition( $type )
    {
        return ( strpos( $type, '/' ) !== false );
    }

    protected function parseAttributeDefinition( $type )
    {
        return explode( '/', $type, 2 );
    }

    protected function datatypeByAttributeDefinition( $def )
    {
        list( $classIdentifier, $attributeIdentifier ) = explode( '/', $def, 2 );
        $class = eZContentClass::fetchByIdentifier( $classIdentifier );
        if ( !$class )
        {
            throw new Exception( "Class '$classIdentifier' does not exist" );
        }
        $classAttributes = $class->attribute( 'data_map' );
        if ( !isset( $classAttributes[$attributeIdentifier] ) )
        {
            throw new Exception( "Attribute '$attributeIdentifier' does not exist in class '$classIdentifier'" );
        }
        $classAttribute = $classAttributes[$attributeIdentifier];
        return $classAttribute->attribute( 'data_type_string' );
    }
}
