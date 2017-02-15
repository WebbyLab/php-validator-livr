<?php

namespace Validator\LIVR;

class Util
{
    public static function isAssocArray($arr)
    {

        if (! is_array($arr)) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
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
