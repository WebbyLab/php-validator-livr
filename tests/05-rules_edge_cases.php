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
}
