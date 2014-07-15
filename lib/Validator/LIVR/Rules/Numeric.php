<?php

namespace Validator\LIVR\Rules;

class Numeric {

    public static function integer() {

        return function($value) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( !is_numeric($value) or !preg_match("/^\-?\d+$/", $value) ) {
                return 'NOT_INTEGER';
            }

            return;
        };
    }

    public static function positive_integer() {

        return function($value) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( !is_numeric($value) or !preg_match( "/^\d+$/", $value) || $value <= 0 ) {
                return 'NOT_POSITIVE_INTEGER';
            }

            return;
        };
    }

    public static function decimal() {

        return function($value) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( !is_numeric($value) or !preg_match("/^\-?[\d.]+$/", $value) ) {
                return 'NOT_DECIMAL';
            }

            return;
        };
    }

    public static function positive_decimal() {

        return function($value) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( !is_numeric($value) or !preg_match("/^[\d.]+$/", $value) or $value <= 0 ) {
                return 'NOT_POSITIVE_DECIMAL';
            }

            return;
        };
    }

    public static function max_number($maxNumer) {

        return function($value) use ($maxNumer) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( $value > $maxNumer ) return 'TOO_HIGH';

            return;
        };
    }


    public static function min_number($minNumer) {

        return function($value) use ($minNumer) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( $value < $minNumer ) return 'TOO_LOW';

            return;
        };
    }


    public static function number_between($minNumer, $maxNumer) {

        return function($value) use ($minNumer, $maxNumer) {
            if ( !isset($value) or $value == '' ) {
                return;
            };

            if ( $value < $minNumer ) return 'TOO_LOW';
            if ( $value > $maxNumer ) return 'TOO_HIGH';

            return;
        };
    }
}
