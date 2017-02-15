<?php

namespace Validator;

class LIVR
{
    /**
     * Current version
     *
     * @var string
     */
    const VERSION = '2.0.0';

    private $isPrepared        = false;
    private $livrRules         = array();
    private $validators        = array();
    private $validatorBuilders = array();
    private $errors            = false;
    private $isAutoTrim        = false;

    private static $IS_DEFAULT_AUTO_TRIM = 0;
    private static $DEFAULT_RULES = array(
        'required'                  => 'Validator\LIVR\Rules\Common::required',
        'not_empty'                 => 'Validator\LIVR\Rules\Common::notEmpty',
        'not_empty_list'            => 'Validator\LIVR\Rules\Common::notEmptyList',
        'any_object'                => 'Validator\LIVR\Rules\Common::anyObject',

        'one_of'                    => 'Validator\LIVR\Rules\Text::oneOf',
        'eq'                        => 'Validator\LIVR\Rules\Text::eq',
        'string'                    => 'Validator\LIVR\Rules\Text::string',
        'min_length'                => 'Validator\LIVR\Rules\Text::minLength',
        'max_length'                => 'Validator\LIVR\Rules\Text::maxLength',
        'length_equal'              => 'Validator\LIVR\Rules\Text::lengthEqual',
        'length_between'            => 'Validator\LIVR\Rules\Text::lengthBetween',
        'like'                      => 'Validator\LIVR\Rules\Text::like',

        'integer'                   => 'Validator\LIVR\Rules\Numeric::integer',
        'positive_integer'          => 'Validator\LIVR\Rules\Numeric::positiveInteger',
        'decimal'                   => 'Validator\LIVR\Rules\Numeric::decimal',
        'positive_decimal'          => 'Validator\LIVR\Rules\Numeric::positiveDecimal',
        'min_number'                => 'Validator\LIVR\Rules\Numeric::minNumber',
        'max_number'                => 'Validator\LIVR\Rules\Numeric::maxNumber',
        'number_between'            => 'Validator\LIVR\Rules\Numeric::numberBetween',

        'email'                     => 'Validator\LIVR\Rules\Special::email',
        'equal_to_field'            => 'Validator\LIVR\Rules\Special::equalToField',
        'url'                       => 'Validator\LIVR\Rules\Special::url',
        'iso_date'                  => 'Validator\LIVR\Rules\Special::isoDate',

        'nested_object'             => 'Validator\LIVR\Rules\Meta::nestedObject',
        'list_of'                   => 'Validator\LIVR\Rules\Meta::listOf',
        'list_of_objects'           => 'Validator\LIVR\Rules\Meta::listOfObjects',
        'list_of_different_objects' => 'Validator\LIVR\Rules\Meta::listOfDifferentObjects',
        'variable_object'           => 'Validator\LIVR\Rules\Meta::variableObject',
        'or'                        => 'Validator\LIVR\Rules\Meta::__or',

        'default'                   => 'Validator\LIVR\Rules\Modifiers::defaultVal',
        'trim'                      => 'Validator\LIVR\Rules\Modifiers::trim',
        'to_lc'                     => 'Validator\LIVR\Rules\Modifiers::toLc',
        'to_uc'                     => 'Validator\LIVR\Rules\Modifiers::toUc',
        'remove'                    => 'Validator\LIVR\Rules\Modifiers::remove',
        'leave_only'                => 'Validator\LIVR\Rules\Modifiers::leaveOnly',
    );


    public static function registerDefaultRules($rules)
    {
        self::$DEFAULT_RULES = self::$DEFAULT_RULES + $rules;
        return;
    }

    public static function registerAliasedDefaultRule($alias)
    {
        if (!$alias['name']) {
            throw new \Exception("Alias name required");
        }

        self::$DEFAULT_RULES[ $alias['name'] ] = self::buildAliasedRule($alias);
        return;
    }

    public static function getDefaultRules()
    {
        return self::$DEFAULT_RULES;
    }

    public static function defaultAutoTrim($isAutoTrim)
    {
        self::$IS_DEFAULT_AUTO_TRIM = !!$isAutoTrim;
    }

    public function __construct($livrRules, $isAutoTrim = false)
    {
        if ($isAutoTrim) {
            $this->isAutoTrim = $isAutoTrim;
        } else {
            $this->isAutoTrim = self::$IS_DEFAULT_AUTO_TRIM;
        }

        $this->livrRules = $livrRules;
        $this->registerRules(self::$DEFAULT_RULES);
    }

    public function prepare()
    {
        if ($this->isPrepared) {
            return;
        }

        foreach ($this->livrRules as $field => $fieldRules) {
            if (!is_array($fieldRules) || \Validator\LIVR\Util::isAssocArray($fieldRules)) {
                $fieldRules = array($fieldRules);
            }

            $validators = array();

            foreach ($fieldRules as $rule) {
                list($name, $args) = $this->parseRule($rule);

                array_push($validators, $this->buildValidator($name, $args));
            }

            $this->validators[$field] = $validators;
        }

        $this->isPrepared = true;
    }


    public function validate($data)
    {
        if (!$this->isPrepared) {
            $this->prepare();
        }

        if (!\Validator\LIVR\Util::isAssocArray($data)) {
            $this->errors = 'FORMAT_ERROR';
            return;
        }

        if ($this->isAutoTrim) {
            $data = $this->autoTrim($data);
        }

        $errors = array();
        $result = array();

        foreach ($this->validators as $fieldName => $validators) {
            if (count($validators) == 0) {
                continue;
            }

            $value = isset($data[$fieldName]) ? $data[$fieldName] : null;

            $isOk = true;
            $fieldResult;

            foreach ($validators as $vCb) {
                $fieldResult = array_key_exists($fieldName, $result) ? $result[$fieldName] : $value;

                $errCode = $vCb(
                    ( array_key_exists($fieldName, $result) ? $result[$fieldName] : $value ),
                    $data,
                    $fieldResult
                );

                if ($errCode) {
                    $errors[$fieldName] = $errCode;
                    $isOk = false;

                    break;
                } elseif ($fieldResult !== null) {
                    $result[$fieldName] = $fieldResult;
                } elseif (array_key_exists($fieldName, $data)) {
                    $result[$fieldName] = $value;
                }
            }
        }

        if (count($errors) > 0) {
            $this->errors = $errors;
            return false;
        } else {
            $this->errors = false;
            return $result;
        }
    }


    public function getErrors()
    {
        return $this->errors;
    }

    public function registerRules($rules)
    {

        $this->validatorBuilders = array_merge($this->validatorBuilders, $rules);

        return $this;
    }

    public function registerAliasedRule($alias)
    {
        if (!$alias['name']) {
            throw new \Exception("Alias name required");
        }

        $this->validatorBuilders[ $alias['name'] ] = self::buildAliasedRule($alias);
    }

    public function getRules()
    {
        return $this->validatorBuilders;
    }

    private function parseRule($livrRule)
    {
        if (\Validator\LIVR\Util::isAssocArray($livrRule)) {
            reset($livrRule);
            $name = key($livrRule);

            $args = $livrRule[$name];

            if (!is_array($args) || \Validator\LIVR\Util::isAssocArray($args)) {
                $args = array($args);
            }
        } else {
             $name = $livrRule;
             $args = array();
        }

        return array($name, $args);
    }


    private function buildValidator($name, $args)
    {
        if (!array_key_exists($name, $this->validatorBuilders)) {
            throw new \Exception("Rule [$name] not registered");
        }

        $funcArgs = $args;
        array_push($funcArgs, $this->validatorBuilders);

        return call_user_func_array($this->validatorBuilders[$name], $funcArgs);
    }

    private static function buildAliasedRule($alias)
    {
        if (!$alias['name']) {
            throw new \Exception("Alias name required");
        }
        if (!$alias['rules']) {
            throw new \Exception("Alias rules required");
        }

        return function ($ruleBuilders) use ($alias) {
            $validator = new \Validator\LIVR(array('value' => $alias['rules']));
            $validator->registerRules($ruleBuilders)->prepare();

            return function ($value, $params, &$outputArr) use ($validator, $alias) {
                $result = $validator->validate(array('value' => $value));

                if ($result) {
                    $outputArr = $result['value'];
                    return;
                } else {
                    $errors = $validator->getErrors();
                    return isset($alias['error']) ? $alias['error'] : $errors['value'];
                }
            };
        };
    }

    private function autoTrim($data)
    {
        if (is_string($data)) {
            return trim($data);
        } elseif (\Validator\LIVR\Util::isAssocArray($data)) {
            $trimmedData = array();
            foreach ($data as $key => $value) {
                $trimmedData[$key]  = $this->autoTrim($value);
            }

            return $trimmedData;
        }
        return $data;
    }
}
