<?php

namespace Validator\LIVR\Rules;

class  Special {

    public static function email() {

        return function($value) {
            if( !isset($value) || $value === '' ) {
                return;
            }

            $emailReg = '/(?:[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*|"(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21\x23-\x5b\x5d-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])*")@(?:(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]*[a-z0-9])?|\[(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?|[a-z0-9-]*[a-z0-9]:(?:[\x01-\x08\x0b\x0c\x0e-\x1f\x21-\x5a\x53-\x7f]|\\[\x01-\x09\x0b\x0c\x0e-\x7f])+)\])/';
            if( !preg_match($emailReg, $value) ) {
                return 'WRONG_EMAIL';
            }

            if ( preg_match('/\@.*\@/', $value ) ){
                return 'WRONG_EMAIL';
            }

            return;
        };
    }

    public static function equal_to_field($field) {

        return function($value, $params) use($field) {
            if( !isset($value) || $value === '' ) {
                return;
            }

            if( $value != $params[$field] ) {
                return 'FIELDS_NOT_EQUAL';
            }

            return;
        };
    }
}

?>