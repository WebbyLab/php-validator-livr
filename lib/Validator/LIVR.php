<?php
namespace Validator;

class LIVR {
    private $isPrepared = false;
    private $livrRules  = [];
    private $validators = [];
    private $validatorBuilders = [];
    private $errors;

    private static $DEFAULT_RULES = [
        'required'       => 'Validator\LIVR\Rules\Common::required',
        'not_empty'      => 'Validator\LIVR\Rules\Common::not_empty',
        
        'one_of'         => 'Validator\LIVR\Rules\String::one_of',
        'min_length'     => 'Validator\LIVR\Rules\String::min_length',
        'max_length'     => 'Validator\LIVR\Rules\String::max_length',
        'length_equal'   => 'Validator\LIVR\Rules\String::length_equal',
        'length_between' => 'Validator\LIVR\Rules\String::length_between',
        'like'           => 'Validator\LIVR\Rules\String::like'
    ];

    
    public static function registerDefaultRules($rules) {
        self::$DEFAULT_RULES = $rules + self::$DEFAULT_RULES;
        return self;
    }

    public static function getDefaultRules() {
        return self::$DEFAULT_RULES;
    }

    public function __construct($livrRules) {
        $this->livrRules = $livrRules;
        $this->registerRules(self::$DEFAULT_RULES); 
    }


    public function prepare() {
        if ( $this->isPrepared ) {
            return;
        }

        $validators = [];
        foreach ( $this->livrRules as $field => $field_rules ) {
            if ( $this->isAssocArray($field_rules) ) {
                $field_rules = [$field_rules];
            }

            foreach ($field_rules as $rule) {
                list($name, $args) = $this->parseRule($rule);

                array_push($validators, $this->buildValidator($name, $args));
            }

            $this->validators[$field] = $validators;
        }
            
        $this->isPrepared = true;
    }


    public function validate($data) {
        if ( $this->isPrepared ) {
            $this->prepare();
        }

        if ( ! $this->isAssocArray($data) ) {
            $this->errors = 'FORMAT_ERROR';
            return;
        }

        $errors = [];
        $result = [];

        foreach ( $this->validators as $fieldName => $validators ) {
            if ( count($this->validators) == 0 ) {
                continue;
            }

            $value = $data[$fieldName];

            $isOk = true;
            $field_result;

            foreach ($validators as $v_cb) {
                $field_result = NULL;
                $errCode = $v_cb( $value, $data, $field_result );

                if ( $errCode ) {
                    $errors[$fieldName] = $errCode;
                    $isOk = false;
                    
                    break;
                }
            }

            if ( $isOk && array_key_exists($fieldName, $data) ) {
                $result[$fieldName] = isset($field_result) ? $field_result  : $value;
            }
        }


        if ( count($errors) > 0 ) {
            $this->errors = $errors;
            return false;
        } else {
            unset($this->errors);
            return $result;
        }
    }


    public function getErrors() {
        return $this->errors;
    }

    public function registerRules($rules) {
        $this->validatorBuilders += $rules;
        return $this;
    }

    public function getRules() {
        return $this->validatorBuilders;
    }

    private function parseRule($livrRule) {
        if ( $this->isAssocArray($livrRule) ) {
            reset($livrRule);
            $name = key($livrRule);

            $args = $livrRule[$name];

            if ( !is_array($args) ) {
                $args = [$args];
            }
        } else {
             $name = $livrRule;
             $args = [];
        }

        return [$name, $args];
    }


    private function buildValidator($name, $args) {
        if ( !array_key_exists($name, $this->validatorBuilders) ) {
            throw new \Exception( "Rule [$name] not registered" );
        }

        $funcArgs = $args;
        array_push($funcArgs, $this->validatorBuilders);

        return call_user_func_array($this->validatorBuilders[$name], $funcArgs);
    }


    private function isAssocArray($arr) {
        if ( ! is_array($arr) ) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}   
