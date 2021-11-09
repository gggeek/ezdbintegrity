<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014-2021
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

class ezdbiEzuserChecker extends ezdbiNullabletypeChecker implements ezdbiDatatypeCheckerInterface
{
    /**
     * (called for each obj attribute)
     */
    public function checkObjectAttribute( array $contentObjectAttribute )
    {
        // we adopt the ez api instead of acting on raw data
        $contentObjectAttribute = new eZContentObjectAttribute( $contentObjectAttribute );

        // for ezuser datatype, the user is always created even if attribute is set to nullable...
        $warnings = array();
        $userid =  $contentObjectAttribute->attribute( 'contentobject_id' );
        $user = $contentObjectAttribute->attribute( 'content' );
        if ( !$user )
        {
            $warnings[] = "No ezuser $userid found" . $this->postfixErrorMsg( $contentObjectAttribute );
        }
        $settings = eZUserSetting::fetch( $userid );
        if ( !$settings )
        {
            $warnings[] = "No settings found for user $userid" . $this->postfixErrorMsg( $contentObjectAttribute );
        }

        return $warnings;
    }
}
