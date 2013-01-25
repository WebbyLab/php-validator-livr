<?php

namespace Validator\LIVR\Rules;

class String {
    public static function one_of($allowed_values) {

        return function($value) use($allowed_values) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( ! in_array($value, $allowed_values) ) {
                return 'NOT_ALLOWED_VALUE';
            }

            return;
        };
    }


    public static function max_length($max_length) {

        return function($value) use($max_length) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") > $max_length ) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function min_length($min_length) {

        return function($value) use($min_length) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") < $min_length ) {
                return 'TOO_SHORT';
            }

            return;
        };
    }


    public static function length_equal($length) {

        return function($value) use($length) {
            if ( !isset($value) or $value == '' ) {
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


     public static function length_between($min_length, $max_length) {

        return function($value) use($min_length, $max_length) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if ( mb_strlen($value, "UTF-8") < $min_length ) {
                return 'TOO_SHORT';
            }

            if ( mb_strlen($value, "UTF-8") > $max_length ) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function like($re) {
        $re = '/' . $re . '/';

        return function($value) use($re) {
            if ( !isset($value) or $value == '' ) {
                return;
            }

            if (! preg_match($re, $value) ) {
                return 'WRONG_FORMAT';
            }

            return;
        };
    }
}
