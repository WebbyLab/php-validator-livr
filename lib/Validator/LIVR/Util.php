<?php

namespace Validator\LIVR;

class Util
{
    public static function isAssocArray($arr)
    {

        if (! is_array($arr)) {
            return false;
        }

        // An empty array is an object too, as PHP decodes {} and [] to the same array
        if (!$arr) {
            return true;
        }

        if (\PHP_VERSION_ID >= 80100) {
            return !\array_is_list($arr);
        }

        // Without array_keys() and range(), which make two arrays
        $index = 0;
        foreach ($arr as $key => $value) {
            if ($key !== $index++) {
                return true;
            }
        }

        return false;
    }

    public static function isList($arr)
    {
        if (!is_array($arr)) {
            return false;
        }

        if ($arr === array()) {
            return true;
        }

        return !self::isAssocArray($arr);
    }

    public static function isStringOrNumber($value)
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return true;
        }

        return false;
    }
}
