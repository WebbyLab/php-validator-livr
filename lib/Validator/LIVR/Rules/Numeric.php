<?php

namespace Validator\LIVR\Rules;

class Numeric
{
    public static function integer()
    {
        return function ($value) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!is_numeric($value) or !preg_match("/^\-?\d+$/", $value)) {
                return 'NOT_INTEGER';
            }

            return;
        };
    }

    public static function positiveInteger()
    {
        return function ($value) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            $opts = array(
                'options' => array(
                    'min_range' => 1
                )
            );

            if (!filter_var($value, FILTER_VALIDATE_INT, $opts)) {
                return 'NOT_POSITIVE_INTEGER';
            }

            return;
        };
    }

    public static function decimal()
    {
        return function ($value) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                return 'NOT_DECIMAL';
            }

            return;
        };
    }

    public static function positiveDecimal()
    {
        return function ($value) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!filter_var($value, FILTER_VALIDATE_FLOAT) or $value <= 0) {
                return 'NOT_POSITIVE_DECIMAL';
            }

            return;
        };
    }

    public static function maxNumber($maxNumer)
    {
        return function ($value) use ($maxNumer) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!is_numeric($value)) {
                return 'NOT_NUMBER';
            }

            if ($value > $maxNumer) {
                return 'TOO_HIGH';
            }

            return;
        };
    }


    public static function minNumber($minNumer)
    {
        return function ($value) use ($minNumer) {
            if (!isset($value) or $value === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!is_numeric($value)) {
                return 'NOT_NUMBER';
            }

            if ($value < $minNumer) {
                return 'TOO_LOW';
            }

            return;
        };
    }


    public static function numberBetween($minNumer, $maxNumer)
    {
        return function ($value) use ($minNumer, $maxNumer) {
            if (!isset($value) or $value === '') {
                return;
            };

            if (!\Validator\LIVR\Util::isStringOrNumber($value)) {
                return 'FORMAT_ERROR';
            }

            if (!is_numeric($value)) {
                return 'NOT_NUMBER';
            }

            if ($value < $minNumer) {
                return 'TOO_LOW';
            }
            if ($value > $maxNumer) {
                return 'TOO_HIGH';
            }

            return;
        };
    }
}
