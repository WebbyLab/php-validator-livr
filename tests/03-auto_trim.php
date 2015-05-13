<?php
require 'vendor/autoload.php';

class AutoTrimTest extends PHPUnit_Framework_TestCase {

    private $rules = array(
        'code'      =>  array('required'),
        'password'  =>  array('required', array( 'min_length' => 3 ) ),
        'address'   =>  array('nested_object' => array(
                            'street' => array( array( 'min_length'=> 5 ) )
                        )
        )
    );

    public function testPositive() {
        print "POSITIVE: Validate data with automatic trim\n";

        $input = array(
            'code'      => ' A ',
            'password'  => ' 123 ',
            'address'   => array(
                'street' => ' hello '
            )
        );

        $output = array(
            'code'      => 'A',
            'password'  => '123',
            'address'   => array(
                'street' => 'hello'
            )
        );

        $validator = new Validator\LIVR( $this->rules, true );
        $cleanData = $validator->validate( $input );

        $this->assertEquals($output, $cleanData);

    }

    public function testNegative() {
        print "NEGATIVE: Validate data with automatic trim\n";

        $input = array(
            'code'      => '   ',
            'password'  => ' 12  ',
            'address'   => array(
                'street'   => '  hell '
            )
        );

        $expectedErrors = array(
            'code'      =>  'REQUIRED',
            'password'  =>  'TOO_SHORT',
            'address'   => array(
                'street'    => 'TOO_SHORT'
            )
        );

        $validator = new Validator\LIVR( $this->rules, true );
        $output = $validator->validate( $input );

        if ($output) {
            throw new \Exception('Should contain error codes');
        }

        $this->assertEquals($expectedErrors, $validator->getErrors());


    }
}


?>