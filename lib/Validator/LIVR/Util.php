<?php

namespace Validator\LIVR;

class Util {

    static public function isAssocArray($arr) {

        if ( ! is_array($arr) ) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    static public function isStringOrNumber($value) {

        if (is_string($value)) {
            return true;
        }
        if (is_int($value)) {
            return true;
        }
        if (is_float($value)) {
            return true;
        }

        return false;
    }
}
