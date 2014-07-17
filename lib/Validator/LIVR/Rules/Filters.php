<?php

namespace Validator\LIVR\Rules;

class Filters {

    public static function trim() {

        return function($value, $undef, &$outputArr) {
            if( !isset($value) || $value === '') {
                return;
            }
            if( is_array($value) ) {
                $result = array();
                foreach ($value as $key => $data) {
                   $result[$key] = trim($data);
                }
                $outputArr = $result;
                return;
            }
            $outputArr = trim($value);
            return;
        };
    }


    public static function to_lc() {

        return function($value, $undef, &$outputArr) {
            if( !isset($value) || $value === '') {
                return;
            }

            if( is_array($value) ) {
                $result = array();
                foreach ($value as $key => $data) {
                   $result[$key] = mb_strtolower($data, "UTF-8");
                }
                $outputArr = $result;

                return;
            }

            $outputArr = mb_strtolower($value, "UTF-8");

            return;
        };
    }


    public static function to_uc() {

        return function($value, $undef, &$outputArr) {
            if( !isset($value) || $value === '') {
                return;
            }

            if( is_array($value) ) {
                $result = array();
                foreach ($value as $key => $data) {
                   $result[$key] = mb_strtoupper($data, "UTF-8");
                }
                $outputArr = $result;

                return;
            }

            $outputArr = mb_strtoupper($value, "UTF-8");

            return;
        };
    }

}



?>
