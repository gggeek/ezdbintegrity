<?php
/**
 * @author G. Giunta
 * @copyright (C) G. Giunta 2014
 * @license Licensed under GNU General Public License v2.0. See file license.txt
 */

interface ezdbiDatatypeCheckerInterface
{
    // singleton style
    public static function instance( eZContentClassAttribute $contentClassAttribute );

    /**
     * Called for each obj attribute.
     * returns an array of problems
     */
    public function checkObjectAttribute( array $contentObjectAttribute );

    /**
     * Called only once
     * returns an array of problems
     * NB: might be introduced later...
     */
    //public static function checkExtraData();

}