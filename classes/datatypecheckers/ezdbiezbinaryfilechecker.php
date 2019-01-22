<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2019
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiEzbinaryfileChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $maxSize;

    public function __construct( eZContentClassAttribute $contentClassAttribute )
    {
        parent::__construct( $contentClassAttribute );

        $this->maxSize = $contentClassAttribute->attribute( eZBinaryFileType::MAX_FILESIZE_FIELD );
    }

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        $binaryFile = $contentObjectAttribute->attribute( 'content' );

        $warnings = array();

        // do not check attributes which do not even contain images
        if ( $binaryFile )
        {
            // get path to original file
            $filePath = $binaryFile->attribute( 'filepath' );

            // check if it is on fs (remember, images are clusterized)
            $file = eZClusterFileHandler::instance( $filePath );
            if ( ! $file->exists() )
            {
                $warnings[] = "Binary file not found: $filePath" . $this->postfixErrorMsg( $contentObjectAttribute );
            }
            else
            {
                // if it is, check its size as well
                if ( $this->maxSize > 0 )
                {
                    $maxSize = $this->maxSize * 1024 * 1024;
                    if ( $file->size() > $maxSize )
                    {
                        $warnings[] = "Binary file larger than {$maxSize} bytes : " . $file->size(). $this->postfixErrorMsg( $contentObjectAttribute );
                    }
                }
            }
        }
        else
        {
            if ( !$this->nullable )
            {
                $warnings[] = "Attribute is null and it should not be". $this->postfixErrorMsg( $contentObjectAttribute );
            }
        }
        return $warnings;
    }
}
