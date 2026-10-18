<?php

require 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;

// The test suite has the other edge cases of these rules
class RulesEdgeCasesTest extends TestCase
{
    public function testListOfDifferentObjectsWithNotList()
    {
        $validator = new Validator\LIVR(array(
            'products' => array('list_of_different_objects' => array('product_type', array(
                'material' => array('product_type' => 'required', 'material_id' => 'positive_integer'),
            ))),
        ));

        $this->assertEquals(array(), $validator->validate(array()), 'Should skip a missing value');
        $this->assertEquals(
            array('products' => ''),
            $validator->validate(array('products' => '')),
            'Should skip an empty value'
        );

        foreach (array('not an array', 10, array('product_type' => 'material')) as $products) {
            $this->assertFalse($validator->validate(array('products' => $products)), 'Should return false');
            $this->assertEquals(array('products' => 'FORMAT_ERROR'), $validator->getErrors(), 'Should return FORMAT_ERROR');
        }
    }

    public function testRegisterDefaultRulesReplacesRules()
    {
        $defaultRules = Validator\LIVR::getDefaultRules();

        Validator\LIVR::registerDefaultRules(array(
            'max_length' => function ($maxLength) use ($defaultRules) {
                $maxLengthRule = call_user_func($defaultRules['max_length'], $maxLength);

                return function ($value) use ($maxLengthRule) {
                    return $maxLengthRule($value) ? 'MY_TOO_LONG' : null;
                };
            },
        ));

        try {
            $validator = new Validator\LIVR(array('phone' => array('max_length' => 10)));
            $this->assertFalse($validator->validate(array('phone' => '123456789123456')), 'Should return false');
            $this->assertEquals(array('phone' => 'MY_TOO_LONG'), $validator->getErrors(), 'Should use the new rule');
        } finally {
            Validator\LIVR::registerDefaultRules(array('max_length' => $defaultRules['max_length']));
        }
    }

    public function testEmptyListsOfRules()
    {
        $validator = new Validator\LIVR(array(
            'no_rules' => array(),
            'list'     => array('list_of' => array()),
        ));

        $this->assertEquals(
            array('list' => array(null, null)),
            $validator->validate(array('no_rules' => 'x', 'list' => array(1, 2))),
            'Should skip fields without rules'
        );
    }
}
