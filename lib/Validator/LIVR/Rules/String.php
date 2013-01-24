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

            if ( strlen($value) > $max_length ) {
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

            if ( strlen($value) < $min_length ) {
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

            if ( strlen($value) < $length ) {
                return 'TOO_SHORT';
            } 

            if ( strlen($value) > $length ) {
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

            if ( strlen($value) < $min_length ) {
                return 'TOO_SHORT';
            } 

            if ( strlen($value) > $max_length ) {
                return 'TOO_LONG';
            } 
            
            return;
        };
    }


    public static function like($re) {

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
