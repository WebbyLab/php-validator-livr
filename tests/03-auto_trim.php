<?php
require 'vendor/autoload.php';

class AutoTrimTest extends PHPUnit_Framework_TestCase {

    private $rules = [
        'code'      =>  ['required'],
        'password'  =>  ['required', [ 'min_length' => 3 ] ],
        'address'   =>  ['nested_object' => [
                            'street' => [ [ 'min_length'=> 5 ] ]
                        ]
        ]
    ];

    public function testPositive() {
        print "POSITIVE: Validate data with automatic trim\n";

        $input = [
            'code'      => ' A ',
            'password'  => ' 123 ',
            'address'   => [
                'street' => ' hello '
            ]
        ];

        $output = [
            'code'      => 'A',
            'password'  => '123',
            'address'   => [
                'street' => 'hello'
            ]
        ];

        $validator = new Validator\LIVR( $this->rules, true );
        $cleanData = $validator->validate( $input );

        $this->assertEquals($output, $cleanData);

    }

    public function testNegative() {
        print "NEGATIVE: Validate data with automatic trim\n";

        $input = [
            'code'      => '   ',
            'password'  => ' 12  ',
            'address'   => [
                'street'   => '  hell '
            ]
        ];

        $expectedErrors = [
            'code'      =>  'REQUIRED',
            'password'  =>  'TOO_SHORT',
            'address'   => [
                'street'    => 'TOO_SHORT'
            ]
        ];

        $validator = new Validator\LIVR( $this->rules, true );
        $output = $validator->validate( $input );

        if($output) {
            throw new \Exception('Should contain error codes');
        }

        $this->assertEquals($expectedErrors, $validator->getErrors());


    }
}


?>