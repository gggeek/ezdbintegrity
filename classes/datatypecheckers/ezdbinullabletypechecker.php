<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2022
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

/**
 * Implements the generic check: attribute is null and according to class definition it can not be
 */
class ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    protected $nullable = false;

    public static function instance( eZContentClassAttribute $contentClassAttribute )
    {
       return new static( $contentClassAttribute );
    }

    public function __construct( $contentClassAttribute )
    {
        $this->nullable = ( ! $contentClassAttribute->attribute( 'is_required' ) ) || $contentClassAttribute->attribute( 'is_information_collector' );
    }

    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        if ( $this->nullable )
        {
            return array();
        }

        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );
        if ( !$contentObjectAttribute->attribute( 'has_content' ) )
        {
            return array( "Attribute is null and it should not be" . $this->postfixErrorMsg( $contentObjectAttribute ) );
        }

        return array();
    }

    protected function postfixErrorMsg( $contentObjectAttribute )
    {
        return " (attribute '" .  $contentObjectAttribute->attribute( 'contentclass_attribute_identifier' ) . "' in object " . $contentObjectAttribute->attribute( 'contentobject_id' ) . ')';
    }
}
