<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Reference;
use Validator\LIVR\Util;
use Validator\LIVR\Values;

/**
 * LIVR 2.5: required_if and when. They refer to other fields of the containing object
 */
class Conditional
{
    public static function requiredIf($conditions = null)
    {
        // conditions and ruleBuilders, the arguments after the conditions are ignored. An empty
        // array is {}: PHP decodes {} and [] to the same array
        if (func_num_args() < 2 || !is_array($conditions) || !$conditions || !Util::isAssocArray($conditions)) {
            throw new \Exception('LIVR: "required_if" takes an object of references to values');
        }

        // Paths and the expected values in the string form, so that 1 matches "1" and true matches
        // "true": a set of strings for each reference
        $paths    = array();
        $expected = array();

        foreach ($conditions as $reference => $values) {
            // PHP turns keys like "1" into integers
            $reference = (string) $reference;
            $paths[]   = Reference::parse($reference, 'required_if');

            // A list of values means "any of". An empty list never matches: a schema error, like ""
            if ($values === array()) {
                throw new \Exception(
                    'LIVR: "required_if" takes a primitive value other than "" or a non-empty list of them for '
                    . json_encode($reference)
                );
            }

            $set = array();
            foreach (Util::isList($values) ? $values : array($values) as $value) {
                if (!Values::isPrimitiveValue($value) || $value === '') {
                    throw new \Exception(
                        'LIVR: "required_if" takes a primitive value other than "" or a list of them for '
                        . json_encode($reference)
                    );
                }

                $set[Values::stringForm($value)] = true;
            }

            $expected[] = $set;
        }

        return function ($value, $params) use ($paths, $expected) {
            if ($value !== null && $value !== '') {
                return;
            }

            // The field is required when every condition matches a non-empty primitive input value
            foreach ($paths as $i => $path) {
                $refValue = Reference::resolve($params, $path);

                if ($refValue === '' || !Values::isPrimitiveValue($refValue)) {
                    return;
                }

                if (!isset($expected[$i][Values::stringForm($refValue)])) {
                    return;
                }
            }

            return 'REQUIRED';
        };
    }

    /**
     * when(reference, condition, rulesIfTrue, [rulesIfFalse], ruleBuilders): the condition form,
     * when(reference, cases, ruleBuilders): the switch form
     */
    public static function when()
    {
        $args = func_get_args();
        $ruleBuilders = array_pop($args);

        if (count($args) < 2) {
            throw new \Exception(
                'LIVR: "when" takes a reference and cases, or a reference, a condition, '
                . 'rules if true and optional rules if false, got ' . count($args) . ' arguments'
            );
        }

        if (count($args) === 2) {
            return self::whenSwitch(Reference::parse($args[0], 'when'), $args[1], $ruleBuilders);
        }

        $path = Reference::parse($args[0], 'when');

        // One validator for the condition and the branches: a validator costs more than its rules.
        // The arguments after the 4th are ignored
        $ruleSets = array_slice($args, 1, 3, true);
        $fieldRules = self::buildRuleSets($ruleSets, $ruleBuilders);

        $conditionRule = $fieldRules[1];
        $rulesIfTrue   = $fieldRules[2];
        $rulesIfFalse  = isset($fieldRules[3]) ? $fieldRules[3] : null;

        return function ($value, $params, &$outputArr) use ($path, $conditionRule, $rulesIfTrue, $rulesIfFalse) {
            $refValue = Reference::resolve($params, $path);

            // The condition rules see the siblings of the field, their output is discarded
            $isTrue = $refValue !== null && $refValue !== '';
            if ($isTrue && $conditionRule) {
                $conditionOutput = null;
                $isTrue = !$conditionRule($refValue, $params, $conditionOutput);
            }

            $branchRule = $isTrue ? $rulesIfTrue : $rulesIfFalse;

            // A false condition without rules if false: the value passes and stays unchanged
            if (!$branchRule) {
                return;
            }

            $output  = null;
            $errCode = $branchRule($value, $params, $output);

            if ($errCode) {
                return $errCode;
            }

            $outputArr = $output;
        };
    }

    private static function whenSwitch($path, $cases, $ruleBuilders)
    {
        // A list is not an object. PHP decodes {} and [] to the same array: it is an empty object
        if (!is_array($cases) || ($cases && !Util::isAssocArray($cases))) {
            throw new \Exception('LIVR: the cases of "when" must be an object of value to rules');
        }

        if (!$cases) {
            throw new \Exception('LIVR: the cases of "when" must not be empty');
        }

        // Keys like "1" are integers in PHP arrays, and lookups by "1" find them
        $caseRules = self::buildRuleSets($cases, $ruleBuilders);

        return function ($value, $params, &$outputArr) use ($path, $caseRules) {
            $refValue = Reference::resolve($params, $path);

            // An empty or unknown referenced value chooses no case. An empty value passes unchanged
            // (the referenced field reports its own errors), any other value would pass unchecked
            if ($refValue === '' || !Values::isPrimitiveValue($refValue)
                || !array_key_exists($caseValue = Values::stringForm($refValue), $caseRules)
            ) {
                return $value === null || $value === '' ? null : 'NOT_ALLOWED_VALUE';
            }

            $branchRule = $caseRules[$caseValue];

            $output  = null;
            $errCode = $branchRule($value, $params, $output);

            if ($errCode) {
                return $errCode;
            }

            $outputArr = $output;
        };
    }

    /**
     * Rule sets in any form that a field accepts, each as one rule (see LIVR::getFieldRule()),
     * all from one validator, with the keys of $ruleSets. An empty list of rules is a schema error
     * (LIVR 2.5, 2.6): "any" is a rule set without rules
     */
    private static function buildRuleSets($ruleSets, $ruleBuilders)
    {
        $livr = array();

        foreach ($ruleSets as $key => $rules) {
            if ($rules === array()) {
                throw new \Exception('LIVR: an empty list of rules in "when", use "any" for no rules');
            }

            $livr["rules$key"] = $rules;
        }

        $validator = new \Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        $fieldRules = array();
        foreach ($ruleSets as $key => $rules) {
            $fieldRules[$key] = $validator->getFieldRule("rules$key");
        }

        return $fieldRules;
    }
}
