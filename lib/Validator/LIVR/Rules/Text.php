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

        // The allowed values as keys: isset() does not go through the whole list like in_array().
        // Booleans are compared as strings (LIVR 2.5, 2.3): true is "true". As in LIVR 2.0, it is
        // also "1", the PHP string form of true (and false is ""), so the values that passed still pass
        $allowedValuesSet = array();
        foreach ($allowedValues as $v) {
            $allowedValuesSet[(string) $v] = true;

            if (is_bool($v)) {
                $allowedValuesSet[$v ? 'true' : 'false'] = true;
            }
        }

        return function ($value) use ($allowedValuesSet) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value) && !is_bool($value)) {
                return 'FORMAT_ERROR';
            }

            if (isset($allowedValuesSet[(string) $value])) {
                return;
            }

            if (is_bool($value) && isset($allowedValuesSet[$value ? 'true' : 'false'])) {
                return;
            }

            return 'NOT_ALLOWED_VALUE';
        };
    }

    public static function eq($allowedValue)
    {
        // Booleans are compared as strings (LIVR 2.5, 2.3): {"eq": true} accepts true and "true",
        // and {"eq": "true"} accepts true. As in LIVR 2.0, the PHP string forms are compared too
        // (true is "1", false is ""), so {"eq": true} still accepts "1" and 1, and {"eq": "1"} true
        $allowedString     = is_array($allowedValue) ? null : (string) $allowedValue;
        $allowedBoolString = is_bool($allowedValue) ? ($allowedValue ? 'true' : 'false') : null;

        return function ($value, $params) use ($allowedString, $allowedBoolString) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value) && !is_bool($value)) {
                return 'FORMAT_ERROR';
            }

            $string = (string) $value;

            if ($string === $allowedString || $string === $allowedBoolString) {
                return;
            }

            if (is_bool($value) && ($value ? 'true' : 'false') === $allowedString) {
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
        // D: $ matches only at the end of the value, as in JavaScript, not before a trailing newline
        $re = '/' . $re . '/D';

        if (func_num_args() >= 3) { #Passed regexp flag, extra arguments are ignored
            $flags = func_get_arg(1);

            if ($flags && $flags != 'i') {
                throw new \Exception("Only 'i' regexp flag supported, but '" . $flags . "' passed");
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
