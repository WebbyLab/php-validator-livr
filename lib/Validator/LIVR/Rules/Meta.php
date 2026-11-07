<?php

namespace Validator\LIVR\Rules;

class Meta
{
    public static function nestedObject($livr, $ruleBuilders)
    {
        // The rule builders are the last argument: extra arguments are ignored, as in LIVR 2.0
        $ruleBuilders = func_get_arg(func_num_args() - 1);

        $validator = new \Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($nestedObject, $params, &$outputArr) use ($validator) {
            if (!isset($nestedObject) || $nestedObject === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isAssocArray($nestedObject)) {
                return 'FORMAT_ERROR';
            }

            $result = $validator->validate($nestedObject);

            if ($result !== false && $result !== null) {
                $outputArr = $result;
                return;
            } else {
                return $validator->getErrors();
            }
        };
    }

    public static function listOf()
    {
        $first_arg = func_get_arg(0);

        if (\Validator\LIVR\Util::isList($first_arg)) {
            // The rule builders are the last argument: extra arguments are ignored
            $livr         = func_get_arg(0);
            $ruleBuilders = func_get_arg(func_num_args() - 1);
        } else {
            $livr         = func_get_args();
            $ruleBuilders = array_pop($livr);
        }

        $validator = new \Validator\LIVR(array('field' => $livr));
        $validateItem = $validator->registerRules($ruleBuilders)->getFieldRule('field');

        return function ($values, $params, &$outputArr) use ($validateItem) {
            if (!isset($values) || $values === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isList($values)) {
                return 'FORMAT_ERROR';
            }

            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($values as $value) {
                // Items have no containing object: a reference in their rules is a missing value
                $output  = null;
                $errCode = $validateItem($value, array(), $output);

                if ($errCode) {
                    $results[] = null;
                    $errors[]  = $errCode;
                    $hasErrors = true;
                } else {
                    $results[] = $output;
                    $errors[]  = null;
                }
            }

            if ($hasErrors) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

    public static function listOfObjects($livr, $ruleBuilders)
    {
        // The rule builders are the last argument: extra arguments are ignored, as in LIVR 2.0
        $ruleBuilders = func_get_arg(func_num_args() - 1);

        $validator = new \Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($objects, $params, &$outputArr) use ($validator) {
            if (!isset($objects) || $objects ==='') {
                return;
            }

            if (!\Validator\LIVR\Util::isList($objects)) {
                return 'FORMAT_ERROR';
            }

            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($objects as $object) {
                $result = $validator->validate($object);

                if ($result !== false && $result !== null) {
                    $errors[] = null;
                    $results[] = $result;
                } else {
                    $hasErrors = true;
                    $errors[] = $validator->getErrors();
                    $results[] = null;
                }
            }

            if ($hasErrors) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

    public static function listOfDifferentObjects($selectorField, $livrs, $ruleBuilders)
    {
        // The rule builders are the last argument: extra arguments are ignored, as in LIVR 2.0
        $ruleBuilders = func_get_arg(func_num_args() - 1);

        $validators = array();

        foreach ($livrs as $selectorValue => $livr) {
            $validator = new \Validator\LIVR($livr);
            $validator->registerRules($ruleBuilders)->prepare();
            $validators[$selectorValue] = $validator;
        }

        return function ($objects, $params, &$outputArr) use ($validators, $selectorField) {
            if (!isset($objects) || $objects === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isList($objects)) {
                return 'FORMAT_ERROR';
            }

            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($objects as $object) {
                $validator = self::selectValidator($validators, $object, $selectorField);

                if (!$validator) {
                    $errors[]  = 'FORMAT_ERROR';
                    $hasErrors = true;
                    continue;
                }

                $result = $validator->validate($object);

                if ($result) {
                    $results[] = $result;
                    $errors[]  = null;
                } else {
                    $results[] = null;
                    $errors[]  = $validator->getErrors();
                    $hasErrors = true;
                }
            }

            if ($hasErrors) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

    public static function variableObject($selectorField, $livrs, $ruleBuilders)
    {
        // The rule builders are the last argument: extra arguments are ignored, as in LIVR 2.0
        $ruleBuilders = func_get_arg(func_num_args() - 1);

        $validators = array();
        foreach ($livrs as $selectorValue => $livr) {
            $validator = new \Validator\LIVR($livr);
            $validator->registerRules($ruleBuilders)->prepare();
            $validators[$selectorValue] = $validator;
        }

        return function ($object, $params, &$outputArr) use ($validators, $selectorField) {
            if (!isset($object) || $object === '') {
                return '';
            }

            $validator = self::selectValidator($validators, $object, $selectorField);

            if (!$validator) {
                return 'FORMAT_ERROR';
            }

            $result = $validator->validate($object);

            if ($result !== false && $result !== null) {
                $outputArr = $result;
                return;
            } else {
                return $validator->getErrors();
            }
        };
    }

    public static function __or()
    {
        // Each argument is a set of rules. Unlike list_of, a list in the first argument
        // is not unwrapped, as a set of rules can be a list: { or: [['email', 'to_lc'], 'positive_integer'] }
        $livrs        = func_get_args();
        $ruleBuilders = array_pop($livrs);

        $fieldRules = array();

        foreach ($livrs as $livr) {
            $validator = new \Validator\LIVR(array('field' => $livr));
            $fieldRules[] = $validator->registerRules($ruleBuilders)->getFieldRule('field');
        }

        return function ($value, $params, &$outputArr) use ($fieldRules) {
            // Empty values go to the rule sets too, so "required" and "not_empty" in them work.
            // The rule sets are transparent: their rules see the siblings of the field
            $lastError = null;

            foreach ($fieldRules as $fieldRule) {
                $output  = null;
                $errCode = $fieldRule($value, $params, $output);

                if (!$errCode) {
                    $outputArr = $output;
                    return;
                }

                $lastError = $errCode;
            }

            if ($lastError) {
                return $lastError;
            }
        };
    }

    /**
     * Returns the validator for the selector value of the object,
     * or null if the object or its selector value is wrong
     */
    private static function selectValidator($validators, $object, $selectorField)
    {
        if (!is_array($object) || !isset($object[$selectorField])) {
            return null;
        }

        // Only strings and integers can be array keys
        $selectorValue = $object[$selectorField];
        if (!is_string($selectorValue) && !is_int($selectorValue)) {
            return null;
        }

        return isset($validators[$selectorValue]) ? $validators[$selectorValue] : null;
    }
}
