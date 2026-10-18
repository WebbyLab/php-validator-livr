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

            return;
        };
    }

    public static function isoDate()
    {
        return function ($value) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (!is_string($value)) {
                return 'FORMAT_ERROR';
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
