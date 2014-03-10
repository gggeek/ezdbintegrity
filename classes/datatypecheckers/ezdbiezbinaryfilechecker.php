<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * @todo check that image size fits within class attribute limits
 * @todo check for mandatory attributes
 */
class ezdbiEzbinaryfileChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        $binaryFile = $contentObjectAttribute->attribute( 'content' );

        // do not check attributes which do not even contain images
        if ( $binaryFile )
        {
            // get path to original file
            $filePath = $binaryFile->attribute( 'filepath' );

            // check if it is on fs (remember, images are clusterized)
            $file = eZClusterFileHandler::instance( $filePath );
            if ( ! $file->exists() )
            {
                return array( "Binary file not found: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }
        }
        else
        {
            if ( !$this->nullable )
            {
                return array( "Attribute is null and it should not be". $this->nullErrorMsg( $contentObjectAttribute ) );
            }
        }
        return array();
    }

    /**
     * (called only once)
     *
     * @todo !important we could implement more checks here, as for extra (dead) lines in ezimage table
     */
    public static function checkExtraData()
    {
    }
}
