<?php

/**
 * Manages definition of schema checks in ini format
 */
class ezdbiIniFormat implements ezdbiSchemaFileFormatInterface
{
    protected $token = '::';

    /**
     * @param string $filename
     * @return ezdbiSchemaChecks
     *
     * @todo manage better ini reading to allow inis outside of standard locations
     */
    public function parseFile( $filename )
    {
        $ini = eZINI::instance( $filename );

        $checks = new ezdbiSchemaChecks();
        foreach( $ini->group( 'ForeignKeys' ) as $table => $value )
        {
            if ( !is_array( $value ) )
            {
                eZDebug::writeWarning( "Error in ini file $filename, var. $table is not an array", __METHOD__ );
                continue;
            }
            foreach( $value as $def )
            {
                $def = explode( $this->token, $def );
                if ( count( $def ) >= 3 )
                {
                    $checks->addForeignKey( $table, $def[0], $def[1], $def[2], ( isset( $def[3] ) ? $def[3] : null ) );
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
        $out = "<?php /*\n";

        $out .= "\n[ForeignKeys]\n";
        foreach( $schemaDef->getForeignKeys() as $def )
        {
            $defs = array( $def['childCol'], $def['parentTable'], $def['parentCol'] );
            if ( $def['exceptions'] != '' )
            {
                $defs[] = $def['exceptions'];
            }
            $out .= $def['childTable'] . '[]=' . implode( $this->token, $defs ) . "\n";
        }

        file_put_contents( $filename, $out );
    }
}