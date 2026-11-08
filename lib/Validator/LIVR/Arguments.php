<?php

namespace Validator\LIVR;

/**
 * Arguments of the LIVR 2.5 rules (LIVR 2.5, 2.6). A rule takes its arguments in order and ignores
 * the ones after them, unchecked. A number and a string are interchangeable: where a rule takes a
 * number, "2" is 2, where it takes a string, 1 is "1"
 */
class Arguments
{
    /**
     * The arguments of a rule builder without ruleBuilders, the last one. {"rule": []} gives
     * one empty array: PHP decodes [] and {} to the same array, and [] is no arguments.
     * {"rule": null} gives one null argument, which no LIVR 2.5 rule takes (LIVR 2.5, 2.6)
     */
    public static function get($args)
    {
        array_pop($args);

        if (count($args) === 1 && $args[0] === array()) {
            return array();
        }

        return $args;
    }

    /**
     * A schema error for a rule without arguments that gets them (LIVR 2.5, 2.6)
     */
    public static function checkNone($args, $ruleName)
    {
        if (count($args) > 1 && self::get($args)) {
            throw new \Exception("LIVR: \"$ruleName\" takes no arguments");
        }
    }

    /**
     * A string argument: a string, or the string form of a number. null for anything else
     * (booleans, null, objects and lists are neither strings nor numbers)
     */
    public static function toString($arg)
    {
        if (is_string($arg)) {
            return $arg;
        }

        return is_int($arg) || is_float($arg) ? Values::stringForm($arg) : null;
    }

    /**
     * A non-negative integer argument: 2, 2.0 (JSON does not tell 2.0 from 2) or "2". null for
     * anything else
     */
    public static function toCount($arg)
    {
        if (is_int($arg)) {
            return $arg >= 0 ? $arg : null;
        }

        if (is_float($arg)) {
            return $arg >= 0 && $arg < PHP_INT_MAX && $arg == floor($arg) ? (int) $arg : null;
        }

        // A string in the grammar of "decimal": "2.0" is 2 too
        if (is_string($arg) && preg_match('/^[0-9]+(?:\.0+)?\z/', $arg)) {
            return self::toCount((float) $arg);
        }

        return null;
    }
}
