<?php
require 'vendor/autoload.php';

class CustomFiltersTest extends PHPUnit_Framework_TestCase {

    public function testPositive() {
        Validator\LIVR::registerDefaultRules([
            'my_ucfirst' => function() {
                return function($value, $params, &$outputRef) {
                    if ( !isset($value) or $value == '' ) {
                        return;
                    }

                    $outputRef = ucfirst($value);
                    return;
                };
            },
            'my_lc' => function() {
                return function($value, $params, &$outputRef) {
                    if ( !isset($value) or $value == '' ) {
                        return;
                    }

                    $outputRef = mb_strtolower($value);
                    return;
                };
            },
            'my_trim' => function() {
               return function($value, $params, &$outputRef) {
                    if ( !isset($value) or $value == '' ) {
                        return;
                    }

                    $outputRef = trim($value);
                    return;
                };
            }
        ]);

        $validator = new Validator\LIVR([
            'word1' => ['my_trim', 'my_lc', 'my_ucfirst'],
            'word2' => ['my_trim', 'my_lc'],
            'word3' => ['my_ucfirst'],
        ]);

        $output = $validator->validate([
            'word1' => ' wordOne ',
            'word2' => ' wordTwo ',
            'word3' => 'wordThree ',
        ]);

        $this->assertEquals($output,[
            'word1' => 'Wordone',
            'word2' => 'wordtwo',
            'word3' => 'WordThree '
            ]);

    }

}

