<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Arguments;

/**
 * Checksum rules (LIVR 2.5, 3.9). The string form is checked as it is: no spaces, dashes or
 * lowercase letters are removed. A boolean gets the error code of the rule, an object or a list
 * is FORMAT_ERROR
 */
class Checksum
{
    public static function luhn()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'luhn');

        return function ($value, $params, &$outputArr) {
            $str = Format::toFormatString($value, 'WRONG_CHECKSUM', $error);
            if ($str === null) {
                return $error;
            }

            if (!preg_match('/^[0-9]{2,}\z/', $str) || !self::hasLuhnChecksum($str)) {
                return 'WRONG_CHECKSUM';
            }

            $outputArr = $str;
        };
    }

    public static function creditCard()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'credit_card');

        // ISO/IEC 7812 card numbers have 12-19 digits. No card network detection
        return function ($value, $params, &$outputArr) {
            $str = Format::toFormatString($value, 'WRONG_CREDIT_CARD_NUMBER', $error);
            if ($str === null) {
                return $error;
            }

            if (!preg_match('/^[0-9]{12,19}\z/', $str) || !self::hasLuhnChecksum($str)) {
                return 'WRONG_CREDIT_CARD_NUMBER';
            }

            $outputArr = $str;
        };
    }

    public static function iban()
    {
        // The only argument is ruleBuilders
        Arguments::checkNone(func_get_args(), 'iban');

        // ISO 13616: a country code, two check digits, 11-30 characters. No per-country lengths
        return function ($value, $params, &$outputArr) {
            $str = Format::toFormatString($value, 'WRONG_IBAN', $error);
            if ($str === null) {
                return $error;
            }

            if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}\z/', $str) || !self::hasIbanChecksum($str)) {
                return 'WRONG_IBAN';
            }

            $outputArr = $str;
        };
    }

    // Luhn mod 10: every second digit from the right is doubled (minus 9 when above 9)
    private static function hasLuhnChecksum($digits)
    {
        $sum = 0;
        $isDoubled = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = ord($digits[$i]) - 48;

            if ($isDoubled) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $isDoubled = !$isDoubled;
        }

        return $sum % 10 === 0;
    }

    // The first four characters moved to the end, letters replaced by two digits (A = 10 ...
    // Z = 35): the number modulo 97 is 1. Computed a character at a time, without big numbers
    private static function hasIbanChecksum($iban)
    {
        $length = strlen($iban);
        $remainder = 0;

        for ($i = 0; $i < $length; $i++) {
            $code = ord($iban[($i + 4) % $length]);

            if ($code <= 57) {
                $remainder = ($remainder * 10 + $code - 48) % 97;
            } else {
                $remainder = ($remainder * 100 + $code - 55) % 97;
            }
        }

        return $remainder === 1;
    }
}
