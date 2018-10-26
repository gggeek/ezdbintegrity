<?php

/**
 * Manages extraction of definition of schema checks in ez persistent object classes
 */
class ezdbiPersistentObjectFormat implements ezdbiSchemaFileFormatInterface
{
    /**
     * @param string $filename in this case it is a class name
     * @return ezdbiSchemaChecks
     * @throws Exception
     */
    public function parseFile( $filename )
    {
        if ( !class_exists( $filename ) )
        {
            throw new Exception( "Can not not analyze ezpo class definition for '$filename': class not found" );
        }

        if( !is_subclass_of( $filename, 'eZPersistentObject' ) )
        {
            throw new Exception( "Can not not analyze class definition for '$filename': not an ezpo" );
        }

        $def = call_user_func( array( $filename, 'definition' ) );
        $checks = new ezdbiSchemaChecks();
        ksort( $def['fields'] );
        foreach( $def['fields'] as $col => $value )
        {
            if ( !isset( $value['foreign_class'] ) )
            {
                continue;
            }
            $checks->addForeignKey( $def['name'], $col, $this->resolveClassToTable( $value['foreign_class'] ), $value['foreign_attribute'] );
        }
        return $checks;
    }

    public function writeFile( $filename, ezdbiSchemaChecks $schemaDef )
    {
        throw new Exception( "Can not not write to ezpo class definition!" );
    }

    protected function resolveClassToTable( $class )
    {
        if ( !class_exists( $class ) )
        {
            throw new Exception( "Can not not analyze ezpo class definition for '$class': class not found" );
        }

        if( !is_subclass_of( $class, 'eZPersistentObject' ) )
        {
            throw new Exception( "Can not not analyze class definition for '$class': not an ezpo" );
        }

        $def = call_user_func( array( $class, 'definition' ) );

        return $def['name'];
    }
}
