<?php

namespace Validator\LIVR\Rules;

class String {

    public static function one_of() {
        $first_arg = func_get_arg(0);

        if ( is_array($first_arg) && !\Validator\LIVR\Util::isAssocArray($first_arg) ) {
            $allowedValues = $first_arg;
        } else {
            $allowedValues = func_get_args();
            array_pop($allowedValues); # pop rule_builders
        }

        return function($value) use($allowedValues) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if ( ! in_array($value, $allowedValues) ) {
                return 'NOT_ALLOWED_VALUE';
            }

            return;
        };
    }


    public static function max_length($maxLength) {

        return function($value) use($maxLength) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") > $maxLength ) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function min_length($minLength) {

        return function($value) use($minLength) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") < $minLength ) {
                return 'TOO_SHORT';
            }

            return;
        };
    }


    public static function length_equal($length) {

        return function($value) use($length) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") < $length ) {
                return 'TOO_SHORT';
            }

            if ( mb_strlen($value, "UTF-8") > $length ) {
                return 'TOO_LONG';
            }

            return;
        };
    }

    public static function length_between($minLength, $maxLength) {

        return function($value) use($minLength, $maxLength) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") < $minLength ) {
                return 'TOO_SHORT';
            }

            if ( mb_strlen($value, "UTF-8") > $maxLength ) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function like($re) {
        $re = '/' . $re . '/';

        return function($value) use($re) {
            if ( !isset($value) or $value === '' ) {
                return;
            }

            if (! preg_match($re, $value) ) {
                return 'WRONG_FORMAT';
            }

            return;
        };
    }
}
