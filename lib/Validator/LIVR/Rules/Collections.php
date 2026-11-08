<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Arguments;
use Validator\LIVR\Util;
use Validator\LIVR\Values;

/**
 * LIVR 2.5: values, keys, list_length, unique and split
 */
class Collections
{
    /**
     * The arguments are the same as in list_of: values('string'), values('required', 'string')
     * or values(['required', 'string'])
     */
    public static function values()
    {
        $validateValue = self::buildItemRule(func_get_args(), 'values', null);

        return function ($object, $params, &$outputArr) use ($validateValue) {
            if ($object === null || $object === '') {
                return;
            }

            // An empty array is {}: PHP decodes {} and [] to the same array
            if (!Util::isAssocArray($object)) {
                return 'FORMAT_ERROR';
            }

            $result = array();
            $errors = array();

            // Values have no containing object: a reference in their rules is a missing value
            foreach ($object as $key => $value) {
                $output  = null;
                $errCode = $validateValue($value, array(), $output);

                if ($errCode) {
                    $errors[$key] = $errCode;
                } elseif (!$errors) {
                    $result[$key] = $output;
                }
            }

            if ($errors) {
                return $errors;
            }

            $outputArr = $result;
        };
    }

    /**
     * The arguments are the same as in list_of: keys(['one_of' => ['uk', 'en']]),
     * keys('required', ['max_length' => 2]) or keys(['required', ['max_length' => 2]])
     */
    public static function keys()
    {
        // Keys are checked as they are: no autoTrim, even when it is the default
        $validateKey = self::buildItemRule(func_get_args(), 'keys', array('autoTrim' => false));

        return function ($object) use ($validateKey) {
            if ($object === null || $object === '') {
                return;
            }

            // An empty array is {}: PHP decodes {} and [] to the same array
            if (!Util::isAssocArray($object)) {
                return 'FORMAT_ERROR';
            }

            $errors = array();

            // The rules only check: their output is ignored and the value stays unchanged. Keys
            // have no containing object: a reference in their rules is a missing value
            foreach ($object as $key => $value) {
                $output = null;

                // PHP turns keys like "1" into integers
                if ($validateKey((string) $key, array(), $output)) {
                    $errors[$key] = 'WRONG_KEY';
                }
            }

            if ($errors) {
                return $errors;
            }
        };
    }

    // The rules of values and keys as one rule, the arguments are the same as in list_of
    private static function buildItemRule($args, $ruleName, $options)
    {
        $ruleBuilders = array_pop($args);

        if (count($args) === 1 && Util::isList($args[0])) {
            $args = $args[0];
        }

        // {"values": null} is one null argument (LIVR 2.5, 2.6), and null in place of a rule is not a rule
        if (!$args || in_array(null, $args, true)) {
            throw new \Exception("LIVR: \"$ruleName\" needs rules");
        }

        $validator = new \Validator\LIVR(array('field' => $args), $options);

        return $validator->registerRules($ruleBuilders)->getFieldRule('field');
    }

    /**
     * list_length(length), list_length(min, max), list_length({ min, max }). Numbers are
     * non-negative integers, also as strings ("2"). The arguments after them are ignored
     */
    public static function listLength()
    {
        $args = Arguments::get(func_get_args());

        $min = 0;
        $max = null;

        if ($args && is_array($args[0])) {
            // An empty array is {}: PHP decodes {} and [] to the same array
            $options = $args[0];

            if (!$options || !Util::isAssocArray($options)) {
                throw new \Exception('LIVR: "list_length" takes non-negative integers as "min" and "max"');
            }

            foreach ($options as $key => $option) {
                if ($key !== 'min' && $key !== 'max') {
                    throw new \Exception("LIVR: \"list_length\" got an unknown option \"$key\"");
                }

                if (Arguments::toCount($option) === null) {
                    throw new \Exception('LIVR: "list_length" takes non-negative integers as "min" and "max"');
                }
            }

            if (isset($options['min'])) {
                $min = Arguments::toCount($options['min']);
            }

            if (isset($options['max'])) {
                $max = Arguments::toCount($options['max']);
            }
        } else {
            $min = $args ? Arguments::toCount($args[0]) : null;
            $max = count($args) > 1 ? Arguments::toCount($args[1]) : $min;

            if ($min === null || $max === null) {
                throw new \Exception(
                    'LIVR: "list_length" takes a length, min and max, or an object with "min" and "max"'
                );
            }
        }

        if ($max !== null && $min > $max) {
            throw new \Exception('LIVR: "list_length" got "min" greater than "max"');
        }

        return function ($list) use ($min, $max) {
            // An empty list is a value: { min: 1 } rejects it
            if ($list === null || $list === '') {
                return;
            }

            if (!Util::isList($list)) {
                return 'FORMAT_ERROR';
            }

            $count = count($list);

            if ($count < $min) {
                return 'TOO_FEW_ITEMS';
            }

            if ($max !== null && $count > $max) {
                return 'TOO_MANY_ITEMS';
            }
        };
    }

    /**
     * unique(): primitive items, unique(...keys): objects with unique values of the keys.
     * Items are compared in the string form, so 1 and "1" are duplicates. A type error wins over
     * duplicates, wherever it is in the list
     */
    public static function unique()
    {
        $keys = Arguments::get(func_get_args());

        // Key names are strings, a number is its string form
        foreach ($keys as $i => $key) {
            $keys[$i] = Arguments::toString($key);

            if ($keys[$i] === null) {
                throw new \Exception('LIVR: "unique" takes key names as strings');
            }
        }

        if (!$keys) {
            return function ($list) {
                if ($list === null || $list === '') {
                    return;
                }

                if (!Util::isList($list)) {
                    return 'FORMAT_ERROR';
                }

                $seen = array();
                $hasDuplicates = false;

                foreach ($list as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }

                    if (!Values::isPrimitiveValue($item)) {
                        return 'FORMAT_ERROR';
                    }

                    $str = Values::stringForm($item);
                    if (isset($seen[$str])) {
                        $hasDuplicates = true;
                    } else {
                        $seen[$str] = true;
                    }
                }

                if ($hasDuplicates) {
                    return 'NOT_UNIQUE_ITEMS';
                }
            };
        }

        return function ($list) use ($keys) {
            if ($list === null || $list === '') {
                return;
            }

            if (!Util::isList($list)) {
                return 'FORMAT_ERROR';
            }

            $seen = array();
            $hasDuplicates = false;

            foreach ($list as $item) {
                if ($item === null || $item === '') {
                    continue;
                }

                if (!Util::isAssocArray($item)) {
                    return 'FORMAT_ERROR';
                }

                // The string forms of the key values with their lengths, so ["a,b"] and ["a", "b"]
                // differ. An item with an empty key value is not compared, a key value of a wrong
                // type is an error
                $itemKey = '';
                $hasEmptyKeyValue = false;

                foreach ($keys as $key) {
                    $keyValue = isset($item[$key]) ? $item[$key] : null;

                    if ($keyValue === null || $keyValue === '') {
                        $hasEmptyKeyValue = true;
                    } elseif (!Values::isPrimitiveValue($keyValue)) {
                        return 'FORMAT_ERROR';
                    } else {
                        $str = Values::stringForm($keyValue);
                        $itemKey .= strlen($str) . ':' . $str;
                    }
                }

                if ($hasEmptyKeyValue) {
                    continue;
                }

                if (isset($seen[$itemKey])) {
                    $hasDuplicates = true;
                } else {
                    $seen[$itemKey] = true;
                }
            }

            if ($hasDuplicates) {
                return 'NOT_UNIQUE_ITEMS';
            }
        };
    }

    public static function split()
    {
        // A non-empty string, a number is its string form. The arguments after it are ignored
        $args = Arguments::get(func_get_args());
        $separator = $args ? Arguments::toString($args[0]) : null;

        if ($separator === null || $separator === '') {
            throw new \Exception('LIVR: "split" takes a non-empty separator string');
        }

        // A modifier: lists, empty values and objects stay as they are, the next rule reports them
        return function ($value, $params, &$outputArr) use ($separator) {
            if (is_string($value)) {
                // At every separator, empty parts are kept: "a,,b" -> ["a", "", "b"]
                if ($value !== '') {
                    $outputArr = explode($separator, $value);
                }
            } elseif (is_int($value) || is_float($value) || is_bool($value)) {
                // In the string form, like a string: 5 -> ["5"], true -> ["true"]
                $outputArr = explode($separator, Values::stringForm($value));
            }
        };
    }
}
