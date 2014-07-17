<?php

namespace Validator\LIVR;

class Util {

    static public function isAssocArray($arr) {

        if ( ! is_array($arr) ) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
