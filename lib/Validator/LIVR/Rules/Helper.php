<?php

namespace Validator\LIVR\Rules;

class Helper {

    public static function nestedObject($livr, $ruleBuilders) {

        $validator = new \Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        return function($nestedObject, $params, &$outputArr) use ($validator) {
            if( !isset($nestedObject) || $nestedObject === '' ) {
                return;
            }

            if( !\Validator\LIVR\Util::isAssocArray($nestedObject) ) {
                return 'FORMAT_ERROR';
            }

            $result = $validator->validate($nestedObject);

            if($result) {
                $outputArr = $result;
                return;
            } else {
                return $validator->getErrors();
            }
        };
    }

    public static function listOf() {
        $first_arg = func_get_arg(0);

        if ( is_array($first_arg) && !\Validator\LIVR\Util::isAssocArray($first_arg) ) {
            $livr         = func_get_arg(0);
            $ruleBuilders = func_get_arg(1);
        } else {
            $livr         = func_get_args();
            $ruleBuilders = array_pop($livr);
        }

        $validator = new \Validator\LIVR( array('field' => $livr) );
        $validator->registerRules($ruleBuilders)->prepare();

        return function($values, $params, &$outputArr) use($validator) {
            if( !isset($values) || $values === '' ) {
                return;
            }

            if( !is_array($values) || \Validator\LIVR\Util::isAssocArray($values) ) {
                return 'FORMAT_ERROR';
            }

            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($values as $value) {
                $result = $validator->validate( array('field' => $value) );

                if($result) {
                    $results[] = $result['field'];
                    $errors[]  = null;
                } else {
                    $results[] = null;
                    $validatorErrors = $validator->getErrors();
                    $errors[]  = $validatorErrors['field'];
                    $hasErrors = true;

                }
            }

            if( $hasErrors ) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

    public static function listOfObjects($livr, $ruleBuilders) {

        $validator = new \Validator\LIVR( $livr );
        $validator->registerRules($ruleBuilders)->prepare();

        return function ($objects, $params, &$outputArr) use($validator) {
            if( !isset($objects) || $objects ==='' ) {
                return;
            }

            if( !is_array($objects) || \Validator\LIVR\Util::isAssocArray($objects) ) {
                return 'FORMAT_ERROR';
            }

            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($objects as $object) {
                $result = $validator->validate($object);

                if($result) {
                    $errors[] = null;
                    $results[] = $result;
                } else {
                    $hasErrors = true;
                    $errors[] = $validator->getErrors();
                    $results[] = null;
                }
            }

            if( $hasErrors ) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

    public static function listOfDifferentObjects($selectorField, $livrs, $ruleBuilders) {

        $validators = array();

        foreach($livrs as $selectorValue => $livr) {
            $validator = new \Validator\LIVR( $livr );
            $validator->registerRules($ruleBuilders)->prepare();
            $validators[$selectorValue] = $validator;
        }

        return function($objects, $params, &$outputArr) use($validators, $selectorField) {
            $results   = array();
            $errors    = array();
            $hasErrors = false;

            foreach ($objects as $object) {

                if( !is_array($object) || !isset( $object[$selectorField] ) || !$validators[ $object[$selectorField] ] ) {
                    $errors[] = 'FORMAT_ERROR';
                    continue;
                }

                $validator = $validators[ $object[$selectorField] ];
                $result = $validator->validate($object);

                if($result) {
                    $results[] = $result;
                    $errors[]  = null;
                } else {
                    $results[] = null;
                    $errors[]  = $validator->getErrors();
                    $hasErrors = true;
                }
            }

            if( $hasErrors ) {
                return $errors;
            } else {
                $outputArr = $results;
                return;
            }
        };
    }

}


?>