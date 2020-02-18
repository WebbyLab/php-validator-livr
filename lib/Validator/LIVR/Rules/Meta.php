<?php

namespace Validator\LIVR\Rules;

class Meta
{
    public static function nestedObject($livr, $ruleBuilders)
    {
        $validator = new \Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($nestedObject, $params, &$outputArr, $context = null) use ($validator) {
            if (!isset($nestedObject) || $nestedObject === '') {
                return;
            }

            if (!\Validator\LIVR\Util::isAssocArray($nestedObject)) {
                return 'FORMAT_ERROR';
            }

            $result = $validator->validate($nestedObject, $context);

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

        if (is_array($first_arg) && !\Validator\LIVR\Util::isAssocArray($first_arg)) {
            $livr         = func_get_arg(0);
            $ruleBuilders = func_get_arg(1);
        } else {
            $livr         = func_get_args();
            $ruleBuilders = array_pop($livr);
        }

        $validator = new \Validator\LIVR(array('field' => $livr));
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($values, $params, &$outputArr, $context = null) use ($validator) {
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
                $result = $validator->validate(array('field' => $value), $context);

                if ($result) {
                    $results[] = $result['field'];
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

        return function ($objects, $params, &$outputArr, $context = null) use ($validator) {
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
                $result = $validator->validate($object, $context);

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

        return function ($objects, $params, &$outputArr, $context = null) use ($validators, $selectorField) {
            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($objects as $object) {
                if (!is_array($object)
                    || !isset($object[$selectorField])
                    || !isset($validators[$object[$selectorField]])
                    || !$validators[$object[$selectorField]]) {
                    $errors[] = 'FORMAT_ERROR';
                    continue;
                }

                $validator = $validators[ $object[$selectorField] ];
                $result = $validator->validate($object, $context);

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

        return function ($object, $params, &$outputArr, $context = null) use ($validators, $selectorField) {
            if (!isset($object) || $object === '') {
                return '';
            }

            if (!is_array($object)
                || !isset($object[$selectorField])
                || !isset($validators[$object[$selectorField]])) {
                return 'FORMAT_ERROR';
            }

            $validator = $validators[ $object[$selectorField] ];
            $result = $validator->validate($object, $context);

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
        $first_arg = func_get_arg(0);

        if (is_array($first_arg) && !\Validator\LIVR\Util::isAssocArray($first_arg)) {
            $livrs        = func_get_arg(0);
            $ruleBuilders = func_get_arg(1);
        } else {
            $livrs        = func_get_args();
            $ruleBuilders = array_pop($livrs);
        }

        $validators = array();

        foreach ($livrs as $livr) {
            $validator = new \Validator\LIVR(array('field' => $livr));
            $validator->registerRules($ruleBuilders)->prepare();
            $validators[] = $validator;
        }

        return function ($value, $params, &$outputArr, $context = null) use ($validators) {
            if (!isset($value) || $value === '') {
                return;
            }

            $lastError = null;
            foreach ($validators as $validator) {
                $result = $validator->validate(array('field' => $value), $context);

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
}
