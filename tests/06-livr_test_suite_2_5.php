<?php

require 'vendor/autoload.php';

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

// The LIVR 2.5 test suite (tests/test_suite_2.5, a copy of test_suite_2.5 from the LIVR repository)
class LIVR25TestSuite extends TestCase
{
    use PHPMock;

    // Cases that need to tell {} from [] (LIVR 2.5, section 6). PHP decodes both to the
    // same array, and the conformance page does not count these cases for PHP
    private static $EMPTY_OBJECT_OR_LIST_CASES = array(
        'positive/35-object_or_list',
        'negative/35-object_or_list',
    );

    /**
     * @dataProvider positiveTestsProvider
     */
    public function testPositive($data, $test)
    {
        $this->skipEmptyObjectOrList($test);

        $validator = $this->buildValidator($data);
        $output = $validator->validate($data['input']);

        $this->assertFalse($validator->getErrors(), 'Validator should contain no errors');
        $this->assertEquals($data['output'], $output, 'Validator should return validated data');
    }

    /**
     * @dataProvider negativeTestsProvider
     */
    public function testNegative($data, $test)
    {
        $this->skipEmptyObjectOrList($test);

        $validator = $this->buildValidator($data);
        $output = $validator->validate($data['input']);

        $this->assertFalse($output ? true : false, 'Validator should return false');
        $this->assertEquals($data['errors'], $validator->getErrors(), 'Validator should contain valid errors');
    }

    /**
     * @dataProvider schemaErrorsProvider
     */
    public function testSchemaError($data, $test)
    {
        try {
            $this->buildValidator($data)->prepare();
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage(), 'The exception should have a message');
            return;
        }

        $this->fail("Building the validator should fail for $test: " . json_encode($data['rules']));
    }

    public function positiveTestsProvider()
    {
        return array_merge(self::readCases('positive'), self::readCases('aliases_positive'));
    }

    public function negativeTestsProvider()
    {
        return array_merge(self::readCases('negative'), self::readCases('aliases_negative'));
    }

    public function schemaErrorsProvider()
    {
        return self::readCases('schema_errors');
    }

    private function buildValidator($data)
    {
        // options.json fixes the current time for the bounds of iso_date and iso_datetime
        if (isset($data['options']['now'])) {
            $now = (new \DateTimeImmutable($data['options']['now']))->getTimestamp();
            $this->getFunctionMock('Validator\LIVR\Rules', 'time')->expects($this->any())->willReturn($now);
        }

        $validator = new Validator\LIVR($data['rules']);

        foreach (isset($data['aliases']) ? $data['aliases'] : array() as $alias) {
            $validator->registerAliasedRule($alias);
        }

        return $validator;
    }

    private function skipEmptyObjectOrList($test)
    {
        if (in_array($test, self::$EMPTY_OBJECT_OR_LIST_CASES, true)) {
            $this->markTestSkipped(
                "$test: PHP decodes {} and [] to the same array, so an empty object can't be told apart "
                . 'from an empty list (not counted for PHP on the LIVR conformance page)'
            );
        }
    }

    // Every case directory of the suite as [data, "suite/case"], the data has a key for each JSON file
    private static function readCases($suite)
    {
        $dir = __DIR__ . "/test_suite_2.5/$suite";
        $cases = array();

        $entries = scandir($dir);
        sort($entries);

        foreach ($entries as $entry) {
            if ($entry[0] === '.' || !is_dir("$dir/$entry")) {
                continue;
            }

            $data = array();
            foreach (glob("$dir/$entry/*.json") as $file) {
                $data[basename($file, '.json')] = json_decode(file_get_contents($file), true);
            }

            $cases["$suite/$entry"] = array($data, "$suite/$entry");
        }

        return $cases;
    }
}

// The date rules read the clock with time() in their namespace. The mock function must be defined
// before the first call there, otherwise PHP binds the call to the global time()
LIVR25TestSuite::defineFunctionMock('Validator\LIVR\Rules', 'time');
