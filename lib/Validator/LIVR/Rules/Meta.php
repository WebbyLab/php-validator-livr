<?php

namespace Validator\LIVR\Rules;

class Meta
{
    public static function nestedObject($livr, $ruleBuilders)
    {
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
            $livr         = func_get_arg(0);
            $ruleBuilders = func_get_arg(1);
        } else {
            $livr         = func_get_args();
            $ruleBuilders = array_pop($livr);
        }

        $validator = new \Validator\LIVR(array('field' => $livr));
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($values, $params, &$outputArr) use ($validator) {
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
                $result = $validator->validate(array('field' => $value));

                // The result is empty without rules
                if ($result !== false && $result !== null) {
                    $results[] = isset($result['field']) ? $result['field'] : null;
                    $errors[]  = null;
                } else {
                    $results[] = null;
                    $validatorErrors = $validator->getErrors();
                    $errors[]  = $validatorErrors['field'];
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

    public static function listOfObjects($livr, $ruleBuilders)
    {
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

        $validators = array();

        foreach ($livrs as $livr) {
            $validator = new \Validator\LIVR(array('field' => $livr));
            $validator->registerRules($ruleBuilders)->prepare();
            $validators[] = $validator;
        }

        return function ($value, $params, &$outputArr) use ($validators) {
            // Empty values go to the rule sets too, so "required" and "not_empty" in them work
            $lastError = null;
            foreach ($validators as $validator) {
                $result = $validator->validate(array('field' => $value));

                if ($result !== false && $result !== null) {
                    $outputArr = $result['field'];
                    return;
                } else {
                    $errors = $validator->getErrors();
                    $lastError = $errors['field'];
                }
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
