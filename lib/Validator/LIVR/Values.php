<?php

namespace Validator\LIVR;

/**
 * Primitive values and their string form (LIVR 2.5, 2.2)
 */
class Values
{
    /**
     * A string, a number or a boolean (LIVR 2.5, 2.2)
     */
    public static function isPrimitiveValue($value)
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }

    /**
     * The string form of a primitive value (LIVR 2.5, 2.2): a string as it is, "true" or "false"
     * for a boolean, a number as in JavaScript String(number): 1.0 is "1", 0.1 is "0.1"
     */
    public static function stringForm($value)
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return self::floatToString($value);
    }

    /**
     * The shortest decimal that reads back as the same double, without an exponent, as
     * JavaScript String(number) for 1e-6 <= |n| < 1e21. (string) $float uses the "precision"
     * setting (14 digits) and an exponent from 1e15, json_encode() writes "1.0e-7"
     */
    public static function floatToString($number)
    {
        // Integral values (-0.0 too) below 10^15: the integer digits
        if ($number > -1e15 && $number < 1e15 && $number == (int) $number) {
            return (string) (int) $number;
        }

        // %g strips trailing zeros: the first precision that reads back is the shortest form
        $repr = sprintf('%.15g', $number);
        if ((float) $repr !== $number) {
            $repr = sprintf('%.16g', $number);
            if ((float) $repr !== $number) {
                $repr = sprintf('%.17g', $number);
            }
        }

        $exponentIndex = strpos($repr, 'e');
        if ($exponentIndex === false) {
            return $repr;
        }

        // "1.5e-7", "1.0e+20": the digits without the point, and the position of the point
        $sign     = $repr[0] === '-' ? '-' : '';
        $mantissa = substr($repr, strlen($sign), $exponentIndex - strlen($sign));
        $digits   = rtrim(str_replace('.', '', $mantissa), '0');
        $point    = 1 + (int) substr($repr, $exponentIndex + 1);

        if ($digits === '') {
            return '0';
        }

        if ($point <= 0) {
            return $sign . '0.' . str_repeat('0', -$point) . $digits;
        }

        if ($point >= strlen($digits)) {
            return $sign . $digits . str_repeat('0', $point - strlen($digits));
        }

        return $sign . substr($digits, 0, $point) . '.' . substr($digits, $point);
    }
}
