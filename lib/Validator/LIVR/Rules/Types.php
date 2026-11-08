<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Arguments;
use Validator\LIVR\Values;

/**
 * LIVR 2.5: boolean, not_one_of, max_decimals and any
 */
class Types
{
    public static function boolean()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'boolean');

        return function ($value, $params, &$outputArr) {
            if ($value === null || $value === '') {
                return;
            }

            // Strict comparison: 1.0 is 1, but "True" and "01" are not booleans
            if ($value === true || $value === 'true' || $value === '1' || $value === 1 || $value === 1.0) {
                $outputArr = true;
                return;
            }

            if ($value === false || $value === 'false' || $value === '0' || $value === 0 || $value === 0.0) {
                $outputArr = false;
                return;
            }

            return Values::isPrimitiveValue($value) ? 'NOT_BOOLEAN' : 'FORMAT_ERROR';
        };
    }

    public static function any()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'any');

        // Any value passes, including objects, lists and empty values, and stays unchanged in the
        // output. Unlike a field without rules, the value is kept
        return function () {
        };
    }

    public static function notOneOf()
    {
        $values = func_get_args();
        array_pop($values); // pop ruleBuilders

        if (!$values) {
            throw new \Exception('LIVR: "not_one_of" takes one or more values');
        }

        // Forbidden values in the string form, so that 1 matches "1" and true matches "true"
        $forbiddenSet = array();
        foreach ($values as $v) {
            if (!Values::isPrimitiveValue($v)) {
                throw new \Exception('LIVR: "not_one_of" takes strings, numbers or booleans');
            }

            $forbiddenSet[Values::stringForm($v)] = true;
        }

        return function ($value) use ($forbiddenSet) {
            if ($value === null || $value === '') {
                return;
            }

            if (!Values::isPrimitiveValue($value)) {
                return 'FORMAT_ERROR';
            }

            if (isset($forbiddenSet[Values::stringForm($value)])) {
                return 'NOT_ALLOWED_VALUE';
            }
        };
    }

    public static function maxDecimals()
    {
        // A non-negative integer: 2, 2.0 or "2". The arguments after it are ignored
        $args = Arguments::get(func_get_args());
        $maxDecimals = $args ? Arguments::toCount($args[0]) : null;

        if ($maxDecimals === null) {
            throw new \Exception('LIVR: "max_decimals" takes a non-negative integer');
        }

        return function ($value, $params, &$outputArr) use ($maxDecimals) {
            if (is_string($value)) {
                if ($value === '') {
                    return;
                }

                // The grammar of "decimal", counted as written without the trailing zeros:
                // "1.230" has 2, "0.10000000000000000001" has 20
                if (!preg_match('/^-?[0-9]+(?:\.([0-9]+))?\z/', $value, $matches)) {
                    return 'NOT_NUMBER';
                }

                // The output is a number: "5" is 5, "1.20" is 1.2. A string too large for a
                // finite double ("1" and 400 zeros) is not a number, JSON has no infinity
                $number = $value + 0;
                if (is_float($number) && !is_finite($number)) {
                    return 'NOT_NUMBER';
                }

                $decimals = isset($matches[1]) ? strlen(rtrim($matches[1], '0')) : 0;

                if ($decimals > $maxDecimals) {
                    return 'TOO_MANY_DECIMALS';
                }

                $outputArr = $number;
                return;
            }

            if (is_int($value)) {
                return;
            }

            if (is_float($value)) {
                // In the string form, so 1.230 has 2 decimals, 1e-7 has 7
                $str = Values::floatToString($value);
                $dotIndex = strpos($str, '.');

                if ($dotIndex !== false && strlen($str) - $dotIndex - 1 > $maxDecimals) {
                    return 'TOO_MANY_DECIMALS';
                }

                return;
            }

            if ($value === null) {
                return;
            }

            return is_bool($value) ? 'NOT_NUMBER' : 'FORMAT_ERROR';
        };
    }
}
