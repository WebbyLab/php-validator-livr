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

    public function testGetFieldRule()
    {
        $validator = new Validator\LIVR(array(
            'name'     => array('required', 'trim', 'to_uc'),
            'no_rules' => array(),
        ));

        $nameRule = $validator->getFieldRule('name');

        $output = null;
        $this->assertNull($nameRule(' ab ', array('name' => ' ab '), $output), 'Should return no error');
        $this->assertSame('AB', $output, 'Should return the output of the last rule');

        $this->assertSame('REQUIRED', $nameRule('', array('name' => ''), $output), 'Should return the error');

        $noRules = $validator->getFieldRule('no_rules');
        $this->assertNull($noRules('x', array('no_rules' => 'x'), $output), 'Should return no error');
        $this->assertNull($output, 'Should return no output for a field without rules');

        // The example from the README
        $validator = new Validator\LIVR(array('tags' => array('my_list_of' => array(array('required', 'to_lc')))));
        $validator->registerRules(array('my_list_of' => function ($rules, $ruleBuilders) {
            $validator = new Validator\LIVR(array('item' => $rules));
            $validateItem = $validator->registerRules($ruleBuilders)->getFieldRule('item');

            return function ($values, $params, &$outputArr) use ($validateItem) {
                $output = array();
                foreach ($values as $value) {
                    $itemOutput = null;
                    $errorCode = $validateItem($value, array('item' => $value), $itemOutput);
                    if ($errorCode) {
                        return $errorCode;
                    }
                    $output[] = $itemOutput;
                }
                $outputArr = $output;
            };
        }));

        $this->assertEquals(array('tags' => array('a', 'b')), $validator->validate(array('tags' => array('A', 'B'))));
        $this->assertFalse($validator->validate(array('tags' => array('A', ''))), 'Should return false');
        $this->assertEquals(array('tags' => 'REQUIRED'), $validator->getErrors(), 'Should return the item error');
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
