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

            if ($value != $params[$field]) {
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

            $isoDateReg = '/^(\d{4})-(\d{2})-(\d{2})$/';

            if (preg_match($isoDateReg, $value)) {
                try {
                    $date = new \DateTime($value);
                    if ($date->format("Y-m-d") == $value) {
                        return;
                    }
                } catch (\Exception $e) {
                    return "WRONG_DATE";
                }
            }

            return "WRONG_DATE";
        };
    }
}
