<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 */
class ezdbiEzstringChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $maxLen;

    public function __construct( eZContentClassAttribute $contentClassAttribute )
    {
        parent::__construct( $contentClassAttribute );

        $this->maxLen = $contentClassAttribute->attribute( eZStringType::MAX_LEN_FIELD );
    }

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );

        // do not check attributes which do not even contain images
        if ( $contentObjectAttribute->attribute( 'has_content' ) )
        {
            if ( $this->maxLen > 0 )
            {
                /// @todo check that this is the appropriate way of counting length of db data
                $textCodec = eZTextCodec::instance( false );
                $stringLength = $textCodec->strlen( $contentObjectAttribute->attribute( 'content' ) );
                if (  $stringLength > $this->maxLen )
                {
                    return array( "String longer than {$this->maxLen} chars: $stringLength" . $this->postfixErrorMsg( $contentObjectAttribute ) );
                }
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
}
