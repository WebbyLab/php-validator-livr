<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Util;

class Common
{

    public static function required()
    {

        return function ($value) {
            if (!isset($value) or $value === '') {
                return 'REQUIRED';
            }
        };
    }

    public static function notEmpty()
    {

        return function ($value) {
            if (isset($value) and $value === '') {
                return 'CANNOT_BE_EMPTY';
            }
        };
    }

    public static function notEmptyList()
    {

        return function ($list) {

            if (!isset($list) || $list === '') {
                return 'CANNOT_BE_EMPTY';
            }

            if (!is_array($list)) {
                return 'WRONG_FORMAT';
            }

            if (count($list) < 1) {
                return 'CANNOT_BE_EMPTY';
            }

            return;
        };
    }

    public static function anyObject()
    {
        return function ($list) {
            if (!isset($list) || $list === '') {
                return;
            }

            if (!Util::isAssocArray($list)) {
                return 'FORMAT_ERROR';
            }

            return;
        };
    }
}
