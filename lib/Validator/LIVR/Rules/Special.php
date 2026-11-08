<?php

namespace Validator\LIVR\Rules;

class Special
{

    public static function email()
    {
        return function ($value) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!is_string($value)) {
                return 'FORMAT_ERROR';
            }

            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'WRONG_EMAIL';
            }

            return;
        };
    }

    public static function equalToField($field)
    {
        return function ($value, $params) use ($field) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            // Compared as strings: "==" finds numeric strings like "10" and "1e1" equal
            $fieldValue = isset($params[$field]) ? $params[$field] : null;

            if (!\Validator\LIVR\Util::isStringOrNumber($fieldValue) || (string) $value !== (string) $fieldValue) {
                return 'FIELDS_NOT_EQUAL';
            }

            return;
        };
    }

    public static function url()
    {
        return function ($value) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!is_string($value)) {
                return 'FORMAT_ERROR';
            }

            $value = mb_strtolower($value, "UTF-8");

            if (!preg_match('/^(http)(s)?/', $value)) {
                return "WRONG_URL";
            }

            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                return 'WRONG_URL';
            }

            // LIVR 2.5: the host does not end with "." and only a port of 1-5 digits, "/", "?" or "#"
            // may follow it. FILTER_VALIDATE_URL accepts "https://example.com." and
            // "https://example.com:8a" (port 8), it checks that labels have at most 63 characters
            // and ports are 0-65535 itself. User info ends at the last "@", as in parse_url()
            $afterHost = '~^[^/?#]*//(?>[^/?#]*@)?(?:\[[^\]]*\]|[^:/?#]*(?<!\.))(?::[0-9]{1,5})?(?:[/?#]|\z)~';

            if (!preg_match($afterHost, $value)) {
                return 'WRONG_URL';
            }

            return;
        };
    }

    public static function isoDate($options = null)
    {
        // The LIVR 2.0 forms get only ruleBuilders, or null for {"iso_date": null}. LIVR 2.5: the
        // first argument, if any, must be an options object, the arguments after it are ignored.
        // {} (or [], PHP decodes both to the same array) and null are the same as no arguments
        if (func_num_args() > 1 && $options !== null) {
            if (!is_array($options) || ($options && !\Validator\LIVR\Util::isAssocArray($options))) {
                throw new \Exception('LIVR: "iso_date" takes no arguments or an object with "min" and "max"');
            }

            if ($options) {
                return \Validator\LIVR\Rules\Dates::isoDateWithBounds($options);
            }
        }

        return function ($value) {
            if (!isset($value) || $value === '') {
                return;
            }

            // A number or a boolean is a primitive value, never a date (LIVR 2.5, 3.3)
            if (!is_string($value)) {
                return \Validator\LIVR\Values::isPrimitiveValue($value) ? 'WRONG_DATE' : 'FORMAT_ERROR';
            }

            // checkdate() instead of DateTime, which is several times slower.
            // \z, not $: "$" also matches before a trailing newline
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\z/', $value, $matches)) {
                // checkdate() starts with year 1. Year 0 is a leap year, like 2000.
                $year = (int) $matches[1] ?: 2000;

                if (checkdate((int) $matches[2], (int) $matches[3], $year)) {
                    return;
                }
            }

            return "WRONG_DATE";
        };
    }
}
