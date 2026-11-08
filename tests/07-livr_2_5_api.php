<?php

require 'vendor/autoload.php';

use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;
use Validator\LIVR;
use Validator\LIVR\Values;

// LIVR 2.5: the clock, the string form of numbers and the PHP types of the output.
// The test suite compares loosely ("1" == 1), so the types are checked here
class LIVR25ApiTest extends TestCase
{
    use PHPMock;

    public function testStringFormOfNumbers()
    {
        // The test vectors of LIVR 2.5, section 2.2
        $vectors = array(
            array(1.0, '1'),
            array(-0.0, '0'),
            array(100, '100'),
            array(1e3, '1000'),
            array(0.1, '0.1'),
            array(-12.25, '-12.25'),
            array(123456789012345, '123456789012345'),
            // The shortest decimal that reads back as the same double, without an exponent
            array(0.1 + 0.2, '0.30000000000000004'),
            array(1e-6, '0.000001'),
            array(1.5e-7, '0.00000015'),
            array(1e15 + 0.5, '1000000000000000.5'),
            array(1.5e20, '150000000000000000000'),
            array(-1e16, '-10000000000000000'),
            array(true, 'true'),
            array(false, 'false'),
            array('1.0', '1.0'),
        );

        foreach ($vectors as $vector) {
            $this->assertSame($vector[1], Values::stringForm($vector[0]), var_export($vector[0], true));
        }
    }

    public function testOutputTypes()
    {
        $validator = new LIVR(array(
            'subscribe'   => 'boolean',
            'unsubscribe' => 'boolean',
            'zip'         => 'digits',
            'amount'      => array('max_decimals' => 2),
            'count'       => array('max_decimals' => 0),
            'starts_at'   => 'iso_datetime',
            'code'        => array('starts_with' => '1'),
            'hex'         => 'hex',
            'ids'         => array('split' => ','),
        ));

        $output = $validator->validate(array(
            'subscribe'   => '1',
            'unsubscribe' => 0.0,
            'zip'         => 1.0,
            'amount'      => '1.20',
            'count'       => '5',
            'starts_at'   => '2026-10-29T10:00:00.123456+02:00',
            'code'        => 12.5,
            'hex'         => 123,
            'ids'         => '1,,2',
        ));

        $this->assertSame(array(
            'subscribe'   => true,
            'unsubscribe' => false,
            'zip'         => '1',
            'amount'      => 1.2,
            'count'       => 5,
            'starts_at'   => '2026-10-29T08:00:00.123Z',
            'code'        => '12.5',
            'hex'         => '123',
            'ids'         => array('1', '', '2'),
        ), $output);
    }

    public function testRelativeBoundsAreTheCurrentDateInUtc()
    {
        $rules = array(
            'date'     => array('iso_date' => array('max' => 'today')),
            'datetime' => array('iso_datetime' => array('min' => 'tomorrow')),
        );
        $input = array('date' => '2026-10-18', 'datetime' => '2026-10-19T00:00:00Z');

        $time = $this->getFunctionMock('Validator\LIVR\Rules', 'time');
        $time->expects($this->any())->willReturnCallback(function () use (&$now) {
            return (new \DateTimeImmutable($now))->getTimestamp();
        });

        // In UTC, never in the local time zone: 23:30 at -02:00 is the next day
        $nows = array('2026-10-18T12:00:00Z' => true, '2026-10-18T23:30:00-02:00' => false);

        foreach ($nows as $now => $isValid) {
            $validator = new LIVR($rules);
            $this->assertSame($isValid, $validator->validate($input) !== false, $now);
        }
    }

    public function testClockIsReadOnValidation()
    {
        $now = '2026-10-18T12:00:00Z';
        $time = $this->getFunctionMock('Validator\LIVR\Rules', 'time');
        $time->expects($this->any())->willReturnCallback(function () use (&$now) {
            return (new \DateTimeImmutable($now))->getTimestamp();
        });

        $validator = new LIVR(array('date' => array('iso_date' => array('min' => 'today'))));
        $this->assertNotFalse($validator->validate(array('date' => '2026-10-18')));

        $now = '2026-10-19T00:00:00Z';
        $this->assertFalse($validator->validate(array('date' => '2026-10-18')));
        $this->assertSame(array('date' => 'DATE_TOO_EARLY'), $validator->getErrors());
    }

    public function testMetaRulesOfLivr20IgnoreExtraArguments()
    {
        // The rule builders are the last argument of a builder: an extra argument must not
        // take their place, or the nested rules lose the custom rules of the validator
        $validator = new LIVR(array(
            'nested'    => array('nested_object' => array(array('code' => 'my_code'), 'extra')),
            'list'      => array('list_of' => array(array('my_code'), 'extra')),
            'objects'   => array('list_of_objects' => array(array('code' => 'my_code'), 'extra')),
            'variable'  => array('variable_object' => array('type', array('a' => array('code' => 'my_code')), 'extra')),
            'different' => array('list_of_different_objects' => array(
                'type',
                array('a' => array('code' => 'my_code')),
                'extra',
            )),
            'like'      => array('like' => array('^a+$', 'i', 'extra')),
        ));

        $validator->registerRules(array('my_code' => function () {
            return function ($value) {
                return $value === 'ok' ? null : 'WRONG_CODE';
            };
        }));

        $this->assertFalse($validator->validate(array(
            'nested'    => array('code' => 'bad'),
            'list'      => array('bad'),
            'objects'   => array(array('code' => 'bad')),
            'variable'  => array('type' => 'a', 'code' => 'bad'),
            'different' => array(array('type' => 'a', 'code' => 'bad')),
            'like'      => 'AAA',
        )));

        $this->assertSame(array(
            'nested'    => array('code' => 'WRONG_CODE'),
            'list'      => array('WRONG_CODE'),
            'objects'   => array(array('code' => 'WRONG_CODE')),
            'variable'  => array('code' => 'WRONG_CODE'),
            'different' => array(array('code' => 'WRONG_CODE')),
        ), $validator->getErrors());
    }

    public function testAutoTrimOption()
    {
        $rules = array('code' => 'required');

        $this->assertSame(array('code' => 'a'), (new LIVR($rules, true))->validate(array('code' => ' a ')));
        $this->assertSame(
            array('code' => 'a'),
            (new LIVR($rules, array('autoTrim' => true)))->validate(array('code' => ' a '))
        );
        $this->assertSame(array('code' => ' a '), (new LIVR($rules))->validate(array('code' => ' a ')));
    }

    public function testKeysAreNotTrimmed()
    {
        LIVR::defaultAutoTrim(true);

        try {
            $validator = new LIVR(array('map' => array('keys' => array('one_of' => array('a')))));
            $this->assertFalse($validator->validate(array('map' => array(' a ' => 1))));
            $this->assertSame(array('map' => array(' a ' => 'WRONG_KEY')), $validator->getErrors());
        } finally {
            LIVR::defaultAutoTrim(false);
        }
    }

    public function testListOfItemsHaveNoContainingObject()
    {
        // LIVR 2.5, section 1: equal_to_field in an item of list_of sees no other fields
        $validator = new LIVR(array(
            'password'  => 'required',
            'passwords' => array('list_of' => array(array('equal_to_field' => 'password'))),
        ));

        $this->assertFalse($validator->validate(array('password' => 'a', 'passwords' => array('a'))));
        $this->assertSame(array('passwords' => array('FIELDS_NOT_EQUAL')), $validator->getErrors());
    }

    public function testEqAndOneOfCompareBooleansAsStrings()
    {
        // LIVR 2.5, 2.3: true is "true" and false is "false", in both directions
        $this->assertBooleanMatches(true, array(true, 'true'));
        $this->assertBooleanMatches(false, array(false, 'false'));
        $this->assertBooleanMatches('true', array(true));
        $this->assertBooleanMatches('false', array(false));

        $this->assertBooleanDoesNotMatch(true, array(false, 'false', 'True', 'TRUE', '0', 0, 'yes'));
        $this->assertBooleanDoesNotMatch(false, array(true, 'true', '1', '0', 0, 'no'));
        $this->assertBooleanDoesNotMatch('true', array(false, '1'));
        $this->assertBooleanDoesNotMatch('false', array(true, '0'));
    }

    public function testEqAndOneOfKeepTheBooleanFormsOfLivr20()
    {
        // LIVR 2.0 compared the PHP string forms: true is "1", false is "". The data that passed
        // still passes (LIVR 2.5, 2.3)
        $this->assertBooleanMatches(true, array('1', 1, 1.0));
        $this->assertBooleanMatches('1', array(true));
        $this->assertBooleanMatches(1, array(true));
        $this->assertBooleanMatches(1.0, array(true));

        $this->assertBooleanDoesNotMatch(false, array('0', 0));
        $this->assertBooleanDoesNotMatch('0', array(false));
        $this->assertBooleanDoesNotMatch(0, array(false));
    }

    public function testOneOfWithBooleansAndOtherValues()
    {
        $validator = new LIVR(array('flag' => array('one_of' => array(array('yes', true, 2)))));

        foreach (array('yes', true, 'true', '1', 1, 2, '2') as $value) {
            $this->assertNotFalse($validator->validate(array('flag' => $value)), var_export($value, true));
        }

        foreach (array('no', false, 'false', '0', 3) as $value) {
            $this->assertFalse($validator->validate(array('flag' => $value)), var_export($value, true));
            $this->assertSame(array('flag' => 'NOT_ALLOWED_VALUE'), $validator->getErrors());
        }
    }

    public function testNullArgumentOfIsoDateIsNoOptions()
    {
        // iso_date ignored its arguments in LIVR 2.0, so {"iso_date": null} stays the plain
        // iso_date (LIVR 2.5, 2.6). PHP decodes {"rule": null} and {"rule": [null]} to the same
        // arguments
        foreach (array(null, array(null)) as $args) {
            $validator = new LIVR(array('date' => array('iso_date' => $args)));
            $this->assertSame(array('date' => '2026-10-18'), $validator->validate(array('date' => '2026-10-18')));
            $this->assertFalse($validator->validate(array('date' => '2026-02-30')));
        }
    }

    public function testNullArgumentOfLivr25RuleIsSchemaError()
    {
        // {"rule": null} is one null argument, which no LIVR 2.5 rule takes (LIVR 2.5, 2.6), and
        // a null in a list of arguments is a wrong argument too
        $schemas = array(
            array('boolean' => null),
            array('any' => null),
            array('digits' => null),
            array('iso_time' => null),
            array('iso_datetime' => null),
            array('uuid' => null),
            array('ip' => null),
            array('unique' => null),
            array('max_decimals' => null),
            array('not_one_of' => array(null)),
            array('not_one_of' => null),
            array('required_if' => array('type' => null)),
            array('list_length' => null),
            array('list_length' => array(null)),
            array('list_length' => array('min' => null)),
            array('list_length' => array('min' => 1, 'max' => null)),
            array('split' => null),
            array('when' => null),
            array('values' => null),
            array('keys' => array('required', null)),
        );

        foreach ($schemas as $rule) {
            try {
                (new LIVR(array('field' => $rule)))->prepare();
            } catch (\Exception $e) {
                continue;
            }

            $this->fail('Should be a schema error: ' . json_encode($rule));
        }

        $this->assertTrue(true);
    }

    public function testNullArgumentOfLivr20RulesAndRegisteredRules()
    {
        // The rules of LIVR 2.0 and the registered rules get {"rule": null} as one null argument,
        // as in LIVR 2.0: {"default": null} sets null (the output keeps the value then)
        $validator = new LIVR(array(
            'missing' => array('default' => null),
            'null'    => array('default' => null),
            'empty'   => array('default' => null),
            'custom'  => array('my_rule' => null),
        ));

        $validator->registerRules(array('my_rule' => function () {
            $args = func_get_args();

            return function ($value) use ($args) {
                return count($args) === 2 && $args[0] === null ? null : 'WRONG_ARGUMENTS';
            };
        }));

        $this->assertSame(
            array('null' => null, 'empty' => '', 'custom' => 'x'),
            $validator->validate(array('null' => null, 'empty' => '', 'custom' => 'x'))
        );

        // The builder of "default" gets null as the default value
        $defaultRule = LIVR::getDefaultRules()['default'];
        $builderArgs = null;

        $validator = new LIVR(array('field' => array('default' => null)));
        $validator->registerRules(array('default' => function () use ($defaultRule, &$builderArgs) {
            $builderArgs = func_get_args();
            return call_user_func_array($defaultRule, $builderArgs);
        }));

        $validator->prepare();
        $this->assertCount(2, $builderArgs);
        $this->assertNull($builderArgs[0]);
    }

    public function testUniqueKeyValueThatIsObjectOrListIsFormatError()
    {
        // Also when another key of the same item is empty (LIVR 2.5, unique)
        $validator = new LIVR(array('items' => array('unique' => array('a', 'b'))));

        $lists = array(
            array(array('a' => '', 'b' => array('x' => 1))),
            array(array('a' => array(1, 2), 'b' => null)),
            array(array('b' => array(1)), array('a' => 1, 'b' => 1)),
            array(array('a' => 1, 'b' => 1), array('a' => 1, 'b' => 1), array('b' => array())),
        );

        foreach ($lists as $list) {
            $this->assertFalse($validator->validate(array('items' => $list)), json_encode($list));
            $this->assertSame(array('items' => 'FORMAT_ERROR'), $validator->getErrors());
        }
    }

    public function testSchemaErrorsAreExceptions()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('"boolean" takes no arguments');

        (new LIVR(array('flag' => array('boolean' => 1))))->prepare();
    }

    private function assertBooleanMatches($value, $allowedValues)
    {
        foreach ($allowedValues as $allowed) {
            foreach (array(array('eq' => $allowed), array('one_of' => array($allowed))) as $rule) {
                $validator = new LIVR(array('field' => $rule));
                $message = var_export($value, true) . ' should pass ' . json_encode($rule);

                $this->assertSame(array('field' => $value), $validator->validate(array('field' => $value)), $message);
            }
        }
    }

    private function assertBooleanDoesNotMatch($value, $allowedValues)
    {
        foreach ($allowedValues as $allowed) {
            foreach (array(array('eq' => $allowed), array('one_of' => array($allowed))) as $rule) {
                $validator = new LIVR(array('field' => $rule));
                $message = var_export($value, true) . ' should fail ' . json_encode($rule);

                $this->assertFalse($validator->validate(array('field' => $value)), $message);
                $this->assertSame(array('field' => 'NOT_ALLOWED_VALUE'), $validator->getErrors(), $message);
            }
        }
    }
}

// The date rules read the clock with time() in their namespace. The mock function must be defined
// before the first call there, otherwise PHP binds the call to the global time()
LIVR25ApiTest::defineFunctionMock('Validator\LIVR\Rules', 'time');
