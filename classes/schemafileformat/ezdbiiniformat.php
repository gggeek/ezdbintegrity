<?php

/**
 * Manages definition of schema checks in ini format
 */
class ezdbiIniFormat implements ezdbiSchemaFileFormatInterface
{
    protected $token = '::';

    /**
     * @param string $filename
     *
     * @todo manage better ini reading to allow inis outside of standard locations
     */
    public function parseFile( $filename )
    {
        $ini = eZINI::instance( $filename );
        $checks = new ezdbiSchemaChecks();
        foreach( $ini->group( 'FKSettings' ) as $table => $value )
        {
            if ( !is_array( $value ) )
            {
                eZDebug::writeWarning( "Error in ini file $filename, var. $table is not an array", __METHOD__ );
                continue;
            }
            foreach( $value as $def )
            {
                $def = explode( $this->token, $def );
                if ( count( $def ) == 3 )
                {
                    $checks->addForeignKey( $table, $def[0], $def[1], $def[2] );
                }
                else
                {
                    eZDebug::writeWarning( "Error in ini file $filename, line in var. $table is not correct", __METHOD__ );
                }
            }
        }
        return $checks;
    }

    public function writeFile( $filename, ezdbiSchemaChecks $schemaDef )
    {

    }
}