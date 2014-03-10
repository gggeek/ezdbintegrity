<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiDatatypeChecker extends ezdbiBaseChecker
{
    protected $type;
    protected $checkerClasses = array();
    protected $limit = 100;

    public function loadDatatypeChecks( $type )
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
            throw new Exception( "No datatype checker '$checker' seem to be registered in ezdbintegrity.ini" );
        }
        $checkerClasses = $ini->variable( 'DataTypeSettings', $checker );
        if ( is_string( $checkerClasses ) )
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

        $this->type = $type;
        $this->checkerClasses = $checkerClasses;
    }

    public function getChecks()
    {
        return $this->checkerClasses;
    }

    public function check()
    {
        $warnings = array();

        // Loop over all class attributes using the datatype:
        $classAttributes = eZContentClassAttribute::fetchList( true, array(
            'version' => eZContentClass::VERSION_STATUS_DEFINED,
            'data_type' => $this->type
        ) );

        $db = eZDB::instance();
        foreach( $classAttributes as $classAttribute )
        {
            $this->output( "Checking attribute '" . $classAttribute->attribute( 'identifier' ) . "' in class " . $classAttribute->attribute( 'contentclass_id' )  );

            $checkers = array();
            foreach( $this->checkerClasses as $key => $checkerClass )
            {
                $checkers[$key] = call_user_func_array( array( $checkerClass, 'instance' ), array( $classAttribute ) );
            }
            $offset = 0;
            do
            {
                $rows = $db->arrayQuery(
                    "SELECT * FROM ezcontentobject_attribute WHERE contentclassattribute_id = " . $classAttribute->attribute( 'id' ),
                    array( 'offset' => $offset, 'limit' => $this->limit ) );
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
            } while ( is_array( $rows ) && count( $rows ) > 0 );
        }

        return $warnings;
    }
}