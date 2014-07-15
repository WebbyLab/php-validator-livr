<?php

require 'vendor/autoload.php';

class TestSuite extends PHPUnit_Framework_TestCase {

    private function verifyPositiveValidation($data) {
        $validator  = new Validator\LIVR( $data['rules'] );

        $output     = $validator->validate( $data['input'] );

        $this->assertFalse( $validator->getErrors(), 'Validator should contain no errors' );
        $this->assertEquals( $output, $data['output'], 'Validator should return validated data' );
    }

    private function verifyNegativeValidation($data) {
        $validator  = new Validator\LIVR( $data['rules'] );
        $output     = $validator->validate( $data['input'] );

        $this->assertFalse($output ? true : false, 'Validator should return false');

        $this->assertEquals( $validator->getErrors(), $data['errors'], 'Validator should contain valid errors' );
    }

    public function testPositiveSuite() {
        $dir = __DIR__ . '/test_suite/positive';

        if ( $handle = opendir( $dir ) ) {

            while ( false !== ( $entry = readdir($handle) ) ) {
                if ( $entry != "." && $entry != ".." ) {

                    $data = array(
                        'input'     => json_decode(file_get_contents("$dir/$entry/input.json"),  true),
                        'rules'     => json_decode(file_get_contents("$dir/$entry/rules.json"),  true),
                        'output'    => json_decode(file_get_contents("$dir/$entry/output.json"), true),
                    );

                    $this->verifyPositiveValidation($data);

                    echo "$entry - OK\n";
                }
            }

            closedir($handle);
        }
    }
}

?>
