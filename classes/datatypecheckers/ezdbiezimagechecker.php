<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * @todo check that image size fits within class attribute limits
 */
class ezdbiEzimageChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    /*public static function instance( eZContentClassAttribute $contentClassAttribute )
    {
        echo "QQQ";
        return new self( $contentClassAttribute );
    }*/

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        $handler = $contentObjectAttribute->attribute( 'content' );

        // do not check attributes which do not even contain images
        if ( $handler->attribute( 'is_valid' ) )
        {
            // get path to original file
            $original = $handler->attribute( 'original' );
            $filePath = $original['full_path'];

            // check if it is on fs (remember, images are clusterized)
            $file = eZClusterFileHandler::instance( $filePath );
            if ( ! $file->exists() )
            {
                return array( "Image file not found: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }

            // check if it is in db
            $image = eZImageFile::fetchByFilepath(
                $contentObjectAttribute->attribute( 'id' ),
                $filePath,
                false
            );
            if ( !$image )
            {
                return array( "Image not found in ezimagefile table: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }
        }
        else
        {
            if ( !$this->nullable )
            {
                return array( "Attribute is null and it should not be" . $this->postfixErrorMsg( $contentObjectAttribute ) );
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
