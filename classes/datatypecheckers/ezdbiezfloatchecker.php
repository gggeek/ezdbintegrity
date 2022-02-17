<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiEzfloatChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $min;
    protected $max;

    public function __construct( eZContentClassAttribute $contentClassAttribute )
    {
        parent::__construct( $contentClassAttribute );

        // talk about a braindead datatype...
        switch( $contentClassAttribute->attribute( eZFloatType::INPUT_STATE_FIELD ) )
        {
            case eZFloatType::HAS_MIN_VALUE:
                $this->min = $contentClassAttribute->attribute( eZFloatType::MAX_FIELD );
                break;
            case eZFloatType::HAS_MAX_VALUE:
                $this->max = $contentClassAttribute->attribute( eZFloatType::MAX_FIELD );
                break;
            case eZFloatType::HAS_MIN_MAX_VALUE:
                $this->min = $contentClassAttribute->attribute( eZFloatType::MIN_FIELD );
                $this->max = $contentClassAttribute->attribute( eZFloatType::MAX_FIELD );
        }
    }

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        $value = $contentObjectAttribute->attribute( 'content' );

        // do not check attributes which do not even contain images
        if ( $contentObjectAttribute->attribute( 'has_content' ) )
        {
            if ( $this->min !== null && $value < $this->min )
            {
                return array( "Float smaller than {$this->min}: $value" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }
            if ( $this->max !== null && $value > $this->max )
            {
                return array( "Float bigger than {$this->max}: $value" . $this->postfixErrorMsg( $contentObjectAttribute ) );
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
