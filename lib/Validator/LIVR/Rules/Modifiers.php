<?php

namespace Validator\LIVR\Rules;

class Modifiers
{
    public static function defaultVal($defaultValue)
    {
        return function ($value, $params, &$outputArr) use ($defaultValue) {
            if (!isset($value) || $value === '') {
                $outputArr = $defaultValue;
            }
        };
    }

    public static function trim()
    {
        return function ($value, $undef, &$outputArr) {
            if (!isset($value) || $value === '') {
                return;
            }
            if (is_array($value)) {
                $result = array();
                foreach ($value as $key => $data) {
                    $result[$key] = trim($data);
                }
                $outputArr = $result;
                return;
            }
            $outputArr = trim($value);
            return;
        };
    }


    public static function toLc()
    {
        return function ($value, $undef, &$outputArr) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (is_array($value)) {
                $result = array();
                foreach ($value as $key => $data) {
                    $result[$key] = mb_strtolower($data, "UTF-8");
                }
                $outputArr = $result;

                return;
            }

            $outputArr = mb_strtolower($value, "UTF-8");

            return;
        };
    }


    public static function toUc()
    {
        return function ($value, $undef, &$outputArr) {
            if (!isset($value) || $value === '') {
                return;
            }

            if (is_array($value)) {
                $result = array();
                foreach ($value as $key => $data) {
                    $result[$key] = mb_strtoupper($data, "UTF-8");
                }
                $outputArr = $result;

                return;
            }

            $outputArr = mb_strtoupper($value, "UTF-8");

            return;
        };
    }

    public static function remove($chars)
    {
        $removeReg = "/[".preg_quote($chars)."]/";

        return function ($value, $undef, &$outputArr) use ($removeReg) {
            if ($value && (is_string($value) || is_numeric($value))) {
                $outputArr = preg_replace($removeReg, '', $value);
            }

            return;
        };
    }

    public static function leaveOnly($chars)
    {
        $leaveOnlyReg = "/[^".preg_quote($chars)."]/";

        return function ($value, $undef, &$outputArr) use ($leaveOnlyReg) {
            if ($value && (is_string($value) || is_numeric($value))) {
                $outputArr = preg_replace($leaveOnlyReg, '', $value);
            }

            return;
        };
    }
}
