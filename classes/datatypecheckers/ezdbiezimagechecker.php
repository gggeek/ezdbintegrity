<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiEzimageChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $maxSize;

    public function __construct( eZContentClassAttribute $contentClassAttribute )
    {
        parent::__construct( $contentClassAttribute );

        $this->maxSize = $contentClassAttribute->attribute( eZImageType::FILESIZE_FIELD );
    }

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        $handler = $contentObjectAttribute->attribute( 'content' );

        $warnings = array();

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
                $warnings[] = array( "Image file not found: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }
            else
            {
                // if it is, check its size as well
                if ( $file->size() == 0 )
                {
                    $warnings[] = "Image file has 0 bytes size" . $this->postfixErrorMsg( $contentObjectAttribute );
                }
                else if ( $this->maxSize > 0 )
                {
                    $maxSize = $this->maxSize * 1024 * 1024;
                    if ( $file->size() > $maxSize )
                    {
                        $warnings[] = "Image file bigger than {$maxSize} bytes : " . $file->size(). $this->postfixErrorMsg( $contentObjectAttribute );
                    }
                }
            }

            // check if it is in custom table in db
            $image = eZImageFile::fetchByFilepath(
                $contentObjectAttribute->attribute( 'id' ),
                $filePath,
                false
            );
            if ( !$image )
            {
                $warnings[] = "Image not found in ezimagefile table: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute );
            }
        }
        else
        {
            if ( !$this->nullable )
            {
                $warnings[] = "Attribute is null and it should not be" . $this->postfixErrorMsg( $contentObjectAttribute );
            }

            // q: are these old images possibly tied to older versions of the content ?
            /*$db = eZDB::instance();
            $count = $db->arrayQuery('select count(*) as leftovers from ezimagefile where contentobject_attribute_id='.$contentObjectAttribute->attribute('id'));
            if($count[0]['leftovers'])
            {
                $warnings[] = "Leftovers in ezimageattribute table" . $this->postfixErrorMsg( $contentObjectAttribute );
            }*/
        }
        return $warnings;
    }
}
