<?php

namespace Validator\LIVR\Rules;

class Common {

    public static function required() {

        return function($value) {
            if ( !isset($value) or $value === '' ) {
                return 'REQUIRED';
            }
        };
    }

    public static function not_empty() {

        return function($value) {
            if ( isset($value) and $value === '' ) {
                return 'CANNOT_BE_EMPTY';
            }
        };
    }

    public static function not_empty_list() {

        return function($list) {

            if( !isset($list) || $list === '' ) {
                return 'CANNOT_BE_EMPTY';
            }

            if( !is_array($list) ) {
                return 'WRONG_FORMAT';
            }

            if( count($list) < 1) {
                return 'CANNOT_BE_EMPTY';
            }

            return;
        };
    }
}
