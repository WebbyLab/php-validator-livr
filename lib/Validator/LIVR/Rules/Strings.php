<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Arguments;
use Validator\LIVR\Values;

/**
 * LIVR 2.5: digits, starts_with, ends_with and contains. They check the string form of a value
 */
class Strings
{
    public static function digits()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'digits');

        // The output is a string, with the leading zeros: "007" stays "007", 7 becomes "7"
        return function ($value, $params, &$outputArr) {
            if (is_string($value)) {
                // ctype_digit() depends on the locale, a regular expression does not
                if ($value !== '' && !preg_match('/^[0-9]+\z/', $value)) {
                    return 'NOT_DIGITS';
                }

                return;
            }

            if ($value === null) {
                return;
            }

            if (!Values::isPrimitiveValue($value)) {
                return 'FORMAT_ERROR';
            }

            // A boolean, a negative or a fractional number has other characters
            $str = Values::stringForm($value);
            if (!preg_match('/^[0-9]+\z/', $str)) {
                return 'NOT_DIGITS';
            }

            $outputArr = $str;
        };
    }

    public static function startsWith()
    {
        $strings = self::affixes(func_get_args(), 'starts_with');

        return function ($value, $params, &$outputArr) use ($strings) {
            if ($value === null || $value === '') {
                return;
            }

            if (!Values::isPrimitiveValue($value)) {
                return 'FORMAT_ERROR';
            }

            // The string form must start with one of the strings, case-sensitive
            $str = Values::stringForm($value);
            foreach ($strings as $prefix) {
                if (strncmp($str, $prefix, strlen($prefix)) === 0) {
                    $outputArr = $str;
                    return;
                }
            }

            return 'WRONG_FORMAT';
        };
    }

    public static function endsWith()
    {
        $strings = self::affixes(func_get_args(), 'ends_with');

        return function ($value, $params, &$outputArr) use ($strings) {
            if ($value === null || $value === '') {
                return;
            }

            if (!Values::isPrimitiveValue($value)) {
                return 'FORMAT_ERROR';
            }

            // The string form must end with one of the strings, case-sensitive
            $str = Values::stringForm($value);
            foreach ($strings as $suffix) {
                $length = strlen($suffix);
                if ($length <= strlen($str) && substr_compare($str, $suffix, -$length) === 0) {
                    $outputArr = $str;
                    return;
                }
            }

            return 'WRONG_FORMAT';
        };
    }

    public static function contains()
    {
        $strings = self::affixes(func_get_args(), 'contains');

        return function ($value, $params, &$outputArr) use ($strings) {
            if ($value === null || $value === '') {
                return;
            }

            if (!Values::isPrimitiveValue($value)) {
                return 'FORMAT_ERROR';
            }

            // The string form must contain one of the strings, case-sensitive
            $str = Values::stringForm($value);
            foreach ($strings as $needle) {
                if (strpos($str, $needle) !== false) {
                    $outputArr = $str;
                    return;
                }
            }

            return 'WRONG_FORMAT';
        };
    }

    /**
     * The arguments of starts_with, ends_with and contains: one or more non-empty strings. A
     * number is its string form: {"starts_with": 1} is "1"
     */
    private static function affixes($args, $ruleName)
    {
        array_pop($args); // pop ruleBuilders

        if (!$args) {
            throw new \Exception("LIVR: \"$ruleName\" takes one or more non-empty strings");
        }

        $strings = array();
        foreach ($args as $arg) {
            $str = Arguments::toString($arg);

            if ($str === null || $str === '') {
                throw new \Exception("LIVR: \"$ruleName\" takes one or more non-empty strings");
            }

            $strings[] = $str;
        }

        return $strings;
    }
}
