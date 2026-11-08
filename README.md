[![Tests](https://github.com/WebbyLab/php-validator-livr/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/WebbyLab/php-validator-livr/actions/workflows/tests.yml)

# NAME
Validator\LIVR - Lightweight validator supporting Language Independent Validation Rules Specification (LIVR)

# SYNOPSIS
Common usage:
```php
    require 'LIVR.php'
    Validator\LIVR::defaultAutoTrim(true);

    $validator  = new Validator\LIVR( [
        'name'      =>  'required',
        'email'     =>  [ 'required', 'email'],
        'gender'    =>  [ 'one_of'     => ['male', 'female'] ],
        'phone'     =>  [ 'max_length' => 10 ],
        'password'  =>  [ 'required', ['min_length' => 10] ],
        'password2' =>  [ 'equal_to_field' => 'password' ]
    ] );

    var $validData = $validator->validate($userData);

    if ($validData) {
        saveUser($validData);
    } else {
        $errors = $validator->getErrors();
    }
```


You can use filters separately or can combine them with validation:
```php
    $validator = new Validator\LIVR([
        'email' => [ 'required', 'trim', 'email', 'to_lc' ]
    ]);
```


Feel free to register your own rules:
```php
    $validator = new Validator\LIVR([
        'password' => ['required', 'strong_password']
    ]);

    $validator->registerRules([ 'strong_password', function() {
        return function($value) {
            // We already have "required" rule to check that the value is present
            if ( !isset($value) || $value === '' ) {
                return;
            }

            if ( strlen($value) < 6 ) {
                return 'WEAK_PASSWORD'
            }
        }
    } ]);
```

# DESCRIPTION
See https://github.com/koorchik/LIVR for details.

Features:

 * Rules are declarative and language independent
 * Any number of rules for each field
 * Return together errors for all fields
 * Excludes all fields that do not have validation rules described
 * Has possibility to validatate complex hierarchical structures
 * Easy to describe and undersand rules
 * Returns understandable error codes(not error messages)
 * Easy to add own rules
 * Rules are be able to change results output ("trim", "nested\_object", for example)
 * Multipurpose (user input validation, configs validation, contracts programming etc)

# LIVR 2.5

LIVR 2.5 is supported. It adds new rules to LIVR 2.0, and every LIVR 2.0 schema keeps working, except for the small fixes of LIVR 2.0 rules below. See the [specification](https://github.com/koorchik/LIVR/blob/master/drafts/v2.5.md), the [changes](https://livr-spec.org/changes.html#v2-5) and the [rules](https://livr-spec.org/validation-rules.html).

Rules added in LIVR 2.5:

 * types and numbers: "boolean", "not\_one\_of", "max\_decimals"
 * strings: "digits", "starts\_with", "ends\_with", "contains"
 * dates and times: "iso\_date" with "min" and "max", "iso\_datetime", "iso\_time"
 * presence and metarules: "required\_if", "when", "values", "keys", "any"
 * lists and modifiers: "list\_length", "unique", "split"
 * formats: "uuid", "ip", "cidr", "hostname", "base64", "hex", "digest", "mongo\_id", "hex\_color", "mac", "e164", "semver", "jwt"
 * checksums: "luhn", "credit\_card", "iban"

A schema with wrong arguments of these rules throws an exception when the validator is built. Arguments after the ones a rule takes are ignored, and a number and a string are interchangeable in them (`{"max_decimals": "2"}`, `{"split": 1}`). `{"rule": null}` is one `null` argument, which none of these rules takes, so `{"boolean": null}` and `{"uuid": null}` throw, like `null` as a value in the arguments (`{"not_one_of": ["a", null]}`). `{"iso_date": null}` is the plain "iso\_date", as in LIVR 2.0. The other LIVR 2.0 rules and the registered rules get `null` as one argument, as before (`{"default": null}`).

Changes of LIVR 2.0 rules in LIVR 2.5:

 * "iso\_date": the first argument, if any, must be an options object or `null` (`{"iso_date": "today"}` throws), a number or a boolean gives "WRONG\_DATE" instead of "FORMAT\_ERROR";
 * "eq" and "one\_of" compare booleans as strings: `{"eq": true}` accepts `true` and `"true"`, `{"eq": "false"}` accepts `false`. They still accept what they accepted in LIVR 2.0, where `true` is `"1"`: `{"eq": true}` accepts `"1"` and `1`, `{"one_of": ["1"]}` accepts `true`;
 * "equal\_to\_field" in aliases and "or" compares with a sibling of the field, in an item of "list\_of" the other field is missing;
 * "url": only a port of 1-5 digits, a path, a query or a fragment may follow the host, so a dot after the host or a port with other characters gives "WRONG\_URL" (`https://www.google.`, `https://example.com./path`, `https://example.com:8a`). Host labels longer than 63 characters and ports above 65535 were already rejected;
 * meta rules ignore extra arguments instead of losing the custom rules of the validator.

PHP decodes `{}` and `[]` to the same empty array, so "values", "keys", "list\_length" and "unique" can't tell an empty object from an empty list, and an object with the keys "0", "1"... is a list.

# INSTALL

Use Composer for installation LIVR. Create composer.json file with following content:
```json
    {
        "require": {
            "validator/livr": "dev-master"
        }
    }
```
# CLASS METHODS

## new Validator\LIVR($livr, $isAutoTrim);
Contructor creates validator objects.
$livr - validations rules. Rules description is available here - https://github.com/koorchik/LIVR

$isAutoTrim - asks validator to trim all values before validation. Output will be also trimmed.
if isAutoTrim is undefined(or null) than defaultAutoTrim value will be used.

## new Validator\LIVR($livr, [ 'autoTrim' => $isAutoTrim ]);
The second argument can be an array of options. "autoTrim" is the same as $isAutoTrim above, but `[ 'autoTrim' => false ]` turns auto trim off even when defaultAutoTrim is on.

## The current date
The bounds "today", "yesterday" and "tomorrow" of "iso\_date" and "iso\_datetime" are the current date in UTC, never in the local time zone. The rules read it with `time()` in the namespace `Validator\LIVR\Rules`, so tests can fix the clock with [php-mock](https://github.com/php-mock/php-mock-phpunit) (a dev dependency only):

```php
    use phpmock\phpunit\PHPMock;

    class BirthDateTest extends PHPUnit\Framework\TestCase
    {
        use PHPMock;

        public function testBirthDateIsInThePast()
        {
            $time = $this->getFunctionMock('Validator\LIVR\Rules', 'time');
            $time->expects($this->any())->willReturn(strtotime('2026-10-18T12:00:00Z'));

            $validator = new Validator\LIVR([ 'birth_date' => [ 'iso_date' => [ 'max' => 'today' ] ] ]);
            $this->assertFalse($validator->validate([ 'birth_date' => '2026-10-19' ]));
        }
    }

    // The mock function must be defined before the rules call time() for the first time
    BirthDateTest::defineFunctionMock('Validator\LIVR\Rules', 'time');
```

## Validator\LIVR::registerDefaultRules([ "rule\_name" => ruleBuilder ])
ruleBuilder - is a function reference which will be called for building single rule validator.
```php
    Validator\LIVR::registerDefaultRules([ 'my_rule' => function($arg1, $arg2, $arg3, $ruleBuilders) {
        // ruleBuilders - are rules from original validator
        // to allow you create new validator with all supported rules
        $validator = new Validator\LIVR($livr);
        $validator->registerRules($ruleBuilders)->prepare();

        return function($value, $allValues, &$outputArr) use ($validator) {
            ...
            if ($notValid) {
                return "SOME_ERROR_CODE";
            }
            else {

            }
        }
    } ]);
```
Then you can use "my\_rule" for validation:
```php
    [
        'name1' => 'my_rule' // Call without parameters
        'name2' => [ 'my_rule'  => arg1 ] // Call with one parameter.
        'name3' => [ 'my_rule'  => [arg1] ] // Call with one parameter.
        'name4' => [ 'my_rule'  => [ arg1, arg2, arg3 ] ] // Call with many parameters.
    ]
```
Here is "max\_number" implemenation:
```php
    function maxNumber($maxNumber) {
        return function($value) use($maxNumber) {
            // We do not validate empty fields. We have "required" rule for this purpose
            if ( !isset($value) || $value === '' ) {
                return;
            }

            // return error message
            if ( $value > $maxNumber ) {
                return 'TOO_HIGH';
            }
        };
    };
    LIVR\Validator->registerDefaultRules([ 'max_number' => $maxNumber ]);
```
All rules for the validator are equal. It does not distinguish "required", "list\_of\_different\_objects" and "trim" rules. So, you can extend validator with any rules you like.

## Validator\LIVR::getDefaultRules();
returns array containing all default ruleBuilders for the validator. You can register new rule or update existing one with "registerRules" method.

## Validator\LIVR::defaultAutoTrim($isAutoTrim)
Enables or disables automatic trim for input data. If is on then every new validator instance will have auto trim option enabled


# OBJECT METHODS

## $validator->validate($input)
Validates user input. On success returns validData (contains only data that has described validation rules). On error return false.
```php
    $validData = $validator->validate($input);
    $errors    = $validator->getErrors();

    if ($errors) {
        // Throw exceptions, write logs, show error messages, etc, using $errors
    } else {
        // Use $vaidData
    }
```
## validator->getErrors()
Returns errors array.
```php
    [
        "field1" => "ERROR_CODE",
        "field2" => "ERROR_CODE",
        ...
    ]
```
For example:
```php
    [
        "country"   =>  "NOT_ALLOWED_VALUE",
        "zip"       =>  "NOT_POSITIVE_INTEGER",
        "street"    =>  "REQUIRED",
        "building"  =>  "NOT_POSITIVE_INTEGER"
    ]
```
## $validator->registerRules(["rule_name" => ruleBuilder])

$ruleBuilder - is a function reference which will be called for building single rule validator.

See "Validator\LIVR::registerDefaultRules" for rules examples.

## $validator->registerAliasedRule([ "name"  => $ruleName, "rules" => $livrRules, "error" => $errorCode ]);
Create custom rules easely and assign own error codes in case own need.
See [rules-aliasing](https://github.com/koorchik/LIVR#rules-aliasing) in LIVR specification.

```php
    $validator->registerAliasedRule([
        "name"  => "adult_age",
        "rules" => [ "positive_integer", ["min_number" => 18] ],
        "error" => "WRONG_AGE"
    ]);
```

## $validator->getRules()
returns array containing all ruleBuilders for the validator. You can register new rule or update existing one with "registerRules" method.

## $validator->getFieldRule($fieldName)
returns all rules of the field as one rule: `function ($value, $allValues, &$output)`, which returns an error code. It runs the rules like "validate" does for the field: in order, each rule gets the output of the previous one. `$output` gets the output of the rules, or null if the field has no rules.

Use it in meta rules that validate many values with the same rules: it skips the result and errors arrays that "validate" makes for every call. Built-in "list\_of" and "or" work this way.

```php
    Validator\LIVR::registerDefaultRules([ 'my_list_of' => function ($rules, $ruleBuilders) {
        $validator = new Validator\LIVR([ 'item' => $rules ]);
        $validateItem = $validator->registerRules($ruleBuilders)->getFieldRule('item');

        return function ($values, $params, &$outputArr) use ($validateItem) {
            $output = [];
            foreach ($values as $value) {
                $itemOutput = null;
                // Items have no containing object: a reference to another field is a missing value
                $errorCode = $validateItem($value, [], $itemOutput);
                if ($errorCode) {
                    return $errorCode;
                }
                $output[] = $itemOutput;
            }
            $outputArr = $output;
        };
    } ]);

    $validator = new Validator\LIVR([ 'tags' => [ 'my_list_of' => [ [ 'required', 'to_lc' ] ] ] ]);
```

# AUTHORS
 * antonfin (Anton Morozov)
 * k0stik (Konstantin Dvornik)
 * k33nice (Alexandr Krykovliuk)
 * koorchik (Viktor Turskyi)
 * wanderer (Danil Greben)

# BUGS
Please report any bugs or feature requests to Github https://github.com/WebbyLab/php-validator-livr

# LICENSE AND COPYRIGHT

Copyright 2012 Viktor Turskyi.

This program is free software; you can redistribute it and/or modify it under the terms of the the Artistic License (2.0). You may obtain a copy of the full license at:

http://www.perlfoundation.org/artistic_license_2_0

Any use, modification, and distribution of the Standard or Modified Versions is governed by this Artistic License. By using, modifying or distributing the Package, you accept this license. Do not use, modify, or distribute the Package, if you do not accept this license.

If your Modified Version has been derived from a Modified Version made by someone other than you, you are nevertheless required to ensure that your Modified Version complies with the requirements of this license.

This license does not grant you the right to use any trademark, service mark, tradename, or logo of the Copyright Holder.

This license includes the non-exclusive, worldwide, free-of-charge patent license to make, have made, use, offer to sell, sell, import and otherwise transfer the Package with respect to any patent claims licensable by the Copyright Holder that are necessarily infringed by the Package. If you institute patent litigation (including a cross-claim or counterclaim) against any party alleging that the Package constitutes direct or contributory patent infringement, then this Artistic License to you shall terminate on the date that such litigation is filed.

Disclaimer of Warranty: THE PACKAGE IS PROVIDED BY THE COPYRIGHT HOLDER AND CONTRIBUTORS "AS IS' AND WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES. THE IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, OR NON-INFRINGEMENT ARE DISCLAIMED TO THE EXTENT PERMITTED BY YOUR LOCAL LAW. UNLESS REQUIRED BY LAW, NO COPYRIGHT HOLDER OR CONTRIBUTOR WILL BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, OR CONSEQUENTIAL DAMAGES ARISING IN ANY WAY OUT OF THE USE OF THE PACKAGE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

