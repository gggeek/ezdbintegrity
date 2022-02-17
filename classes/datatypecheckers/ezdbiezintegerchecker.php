<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiEzintegerChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $min;
    protected $max;

    public function __construct( eZContentClassAttribute $contentClassAttribute )
    {
        parent::__construct( $contentClassAttribute );

        // talk about a braindead datatype...
        switch( $contentClassAttribute->attribute( eZIntegerType::INPUT_STATE_FIELD ) )
        {
            case eZIntegerType::HAS_MIN_VALUE:
                $this->min = $contentClassAttribute->attribute( eZIntegerType::MAX_VALUE_FIELD );
                break;
            case eZIntegerType::HAS_MAX_VALUE:
                $this->max = $contentClassAttribute->attribute( eZIntegerType::MAX_VALUE_FIELD );
                break;
            case eZIntegerType::HAS_MIN_MAX_VALUE:
                $this->min = $contentClassAttribute->attribute( eZIntegerType::MIN_VALUE_FIELD );
                $this->max = $contentClassAttribute->attribute( eZIntegerType::MAX_VALUE_FIELD );
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
                return array( "Integer smaller than {$this->min}: $value" . $this->postfixErrorMsg( $contentObjectAttribute ) );
            }
            if ( $this->max !== null && $value > $this->max )
            {
                return array( "Integer bigger than {$this->max}: $value" . $this->postfixErrorMsg( $contentObjectAttribute ) );
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
