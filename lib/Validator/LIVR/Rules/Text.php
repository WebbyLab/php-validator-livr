<?php

namespace Validator\LIVR\Rules;

class Text
{
    public static function oneOf()
    {
        $first_arg = func_get_arg(0);

        if (is_array($first_arg) && !\Validator\LIVR\Util::isAssocArray($first_arg)) {
            $allowedValues = $first_arg;
        } else {
            $allowedValues = func_get_args();
            array_pop($allowedValues); # pop rule_builders
        }

        $modifiedAllowedValues = array();
        foreach ($allowedValues as $v) {
            $modifiedAllowedValues[] = (string) $v;
        }

        return function ($value) use ($modifiedAllowedValues) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value) && !is_bool($value)) {
                return 'FORMAT_ERROR';
            }

            if (! in_array((string) $value, $modifiedAllowedValues, true)) {
                return 'NOT_ALLOWED_VALUE';
            }

            return;
        };
    }

    public static function eq($allowedValue)
    {
        return function ($value, $params) use ($allowedValue) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value) && !is_bool($value)) {
                return 'FORMAT_ERROR';
            }

            if ((string)$value === (string)$allowedValue) {
                return;
            }

            return 'NOT_ALLOWED_VALUE';
        };
    }

    public static function string()
    {
        return function ($value, $params) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value) && !is_bool($value)) {
                return 'FORMAT_ERROR';
            }

            return;
        };
    }

    public static function maxLength($maxLength)
    {

        return function ($value) use ($maxLength) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (mb_strlen($value, "UTF-8") > $maxLength) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function minLength($minLength)
    {

        return function ($value) use ($minLength) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (mb_strlen($value, "UTF-8") < $minLength) {
                return 'TOO_SHORT';
            }

            return;
        };
    }


    public static function lengthEqual($length)
    {

        return function ($value) use ($length) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (mb_strlen($value, "UTF-8") < $length) {
                return 'TOO_SHORT';
            }

            if (mb_strlen($value, "UTF-8") > $length) {
                return 'TOO_LONG';
            }

            return;
        };
    }

    public static function lengthBetween($minLength, $maxLength)
    {

        return function ($value) use ($minLength, $maxLength) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (mb_strlen($value, "UTF-8") < $minLength) {
                return 'TOO_SHORT';
            }

            if (mb_strlen($value, "UTF-8") > $maxLength) {
                return 'TOO_LONG';
            }

            return;
        };
    }


    public static function like($re)
    {
        $re = '/' . $re . '/';

        if (func_num_args() == 3) { #Passed regexp flag
            $flags = func_get_arg(1);

            if ($flags && $flags != 'i') {
                throw new Exception("Only 'i' regexp flag supported, but '" . $flags . "' passed");
            }

            $re .= $flags;
        };

        return function ($value) use ($re) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (! preg_match($re, $value)) {
                return 'WRONG_FORMAT';
            }

            return;
        };
    }
}
