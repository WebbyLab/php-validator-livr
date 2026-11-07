<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Arguments;
use Validator\LIVR\Values;

/**
 * Format rules (LIVR 2.5, 3.8). They check the string form of a value and output it. A boolean
 * gets the error code of the rule (true is not a hostname, "true" is), an object or a list is
 * FORMAT_ERROR. All patterns end with \z: "$" also matches before a trailing newline
 */
class Format
{
    // Four numbers 0-255 without leading zeros: some systems read "010" as octal
    const IPV4_NUMBER = '(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])';
    const IPV4 = '/^(?:' . self::IPV4_NUMBER . '\.){3}' . self::IPV4_NUMBER . '\z/';

    public static function uuid()
    {
        $versions = Arguments::get(func_get_args());

        if (!$versions) {
            // Any version 1-8 with the variant 8, 9, a or b, the nil and the max UUID
            $re = '/^(?:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-8][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}'
                . '|00000000-0000-0000-0000-000000000000|[fF]{8}-[fF]{4}-[fF]{4}-[fF]{4}-[fF]{12})\z/';

            return self::buildRule($re, 'WRONG_UUID');
        }

        // Versions are strings "v1"-"v8", like in ip and cidr: 4, "4" or "V4" is a schema error
        $digits = '';
        foreach ($versions as $version) {
            if (!is_string($version) || !preg_match('/^v[1-8]\z/', $version)) {
                throw new \Exception('LIVR: "uuid" takes versions "v1"-"v8", got ' . json_encode($version));
            }

            $digits .= $version[1];
        }

        $re = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[' . $digits . '][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}'
            . '-[0-9a-fA-F]{12}\z/';

        return self::buildRule($re, 'WRONG_UUID');
    }

    public static function ip()
    {
        $version = self::ipVersion(func_get_args(), 'ip');

        return function ($value, $params, &$outputArr) use ($version) {
            $str = self::toFormatString($value, 'WRONG_IP', $error);
            if ($str === null) {
                return $error;
            }

            if (!self::isIp($str, $version)) {
                return 'WRONG_IP';
            }

            $outputArr = $str;
        };
    }

    public static function cidr()
    {
        $version = self::ipVersion(func_get_args(), 'cidr');

        return function ($value, $params, &$outputArr) use ($version) {
            $str = self::toFormatString($value, 'WRONG_CIDR', $error);
            if ($str === null) {
                return $error;
            }

            // An address never has "/". Host bits may be set: "10.0.0.1/8" passes
            $slashIndex = strpos($str, '/');
            if ($slashIndex === false) {
                return 'WRONG_CIDR';
            }

            $address = substr($str, 0, $slashIndex);
            $prefixLength = (string) substr($str, $slashIndex + 1);

            if ($version !== 6 && preg_match(self::IPV4, $address)) {
                $maxPrefixLength = 32;
            } elseif ($version !== 4 && self::isIPv6($address)) {
                $maxPrefixLength = 128;
            } else {
                return 'WRONG_CIDR';
            }

            // Decimal without leading zeros
            if (!preg_match('/^(?:0|[1-9][0-9]{0,2})\z/', $prefixLength) || $prefixLength > $maxPrefixLength) {
                return 'WRONG_CIDR';
            }

            $outputArr = $str;
        };
    }

    public static function hostname()
    {
        Arguments::checkNone(func_get_args(), 'hostname');

        // RFC 1123 labels of 1-63 characters, not starting or ending with "-", no trailing dot, at
        // most 253 characters. The last label is not all digits, so 192.0.2.1 is not a hostname
        $re = '/^(?=.{1,253}\z)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*'
            . '(?![0-9]+\z)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/s';

        return self::buildRule($re, 'WRONG_HOSTNAME');
    }

    public static function base64()
    {
        $args = Arguments::get(func_get_args());

        if (!$args) {
            // Groups of 4 characters, the padding "=" or "==" only at the end
            $re = '/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?\z/';
        } elseif ($args[0] === 'url') {
            // The arguments after the variant are ignored
            // The URL-safe alphabet, the padding is either complete or absent
            $re = '/^(?:[A-Za-z0-9_-]{4})*(?:[A-Za-z0-9_-]{2}(?:==)?|[A-Za-z0-9_-]{3}=?)?\z/';
        } else {
            throw new \Exception('LIVR: "base64" takes no arguments or "url"');
        }

        return self::buildRule($re, 'WRONG_BASE64');
    }

    public static function hex()
    {
        Arguments::checkNone(func_get_args(), 'hex');

        return self::buildRule('/^[0-9a-fA-F]+\z/', 'WRONG_HEX');
    }

    public static function digest()
    {
        $algorithms = func_get_args();
        array_pop($algorithms); // pop ruleBuilders

        $hexLengths = array('md5' => 32, 'sha1' => 40, 'sha256' => 64, 'sha384' => 96, 'sha512' => 128);

        if (!$algorithms) {
            throw new \Exception('LIVR: "digest" needs one or more algorithms');
        }

        $lengths = array();
        foreach ($algorithms as $algorithm) {
            // A number is its string form, never an algorithm
            if (!is_string($algorithm) || !isset($hexLengths[$algorithm])) {
                throw new \Exception('LIVR: "digest" got an unknown algorithm ' . json_encode($algorithm));
            }

            $lengths[] = $hexLengths[$algorithm];
        }

        $re = '/^(?:' . implode('|', array_map(function ($length) {
            return '[0-9a-fA-F]{' . $length . '}';
        }, array_unique($lengths))) . ')\z/';

        return self::buildRule($re, 'WRONG_DIGEST');
    }

    public static function mongoId()
    {
        Arguments::checkNone(func_get_args(), 'mongo_id');

        return self::buildRule('/^[0-9a-fA-F]{24}\z/', 'WRONG_MONGO_ID');
    }

    public static function hexColor()
    {
        Arguments::checkNone(func_get_args(), 'hex_color');

        return self::buildRule('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})\z/', 'WRONG_HEX_COLOR');
    }

    public static function mac()
    {
        $args = Arguments::get(func_get_args());

        // 48 bits are six pairs (MAC-48, EUI-48), 64 bits are eight pairs (EUI-64). 48.0 and "48"
        // are 48. The arguments after it are ignored
        $bits = $args ? Arguments::toCount($args[0]) : 48;
        if ($bits !== 48 && $bits !== 64) {
            throw new \Exception('LIVR: "mac" takes no arguments, 48 or 64');
        }

        // The back reference keeps the first separator
        $re = '/^[0-9a-fA-F]{2}([:-])[0-9a-fA-F]{2}(?:\1[0-9a-fA-F]{2}){' . ($bits == 48 ? 4 : 6) . '}\z/';

        return self::buildRule($re, 'WRONG_MAC');
    }

    public static function e164()
    {
        Arguments::checkNone(func_get_args(), 'e164');

        return self::buildRule('/^\+[1-9][0-9]{6,14}\z/', 'WRONG_E164');
    }

    public static function semver()
    {
        Arguments::checkNone(func_get_args(), 'semver');

        // The regular expression of semver.org 2.0.0 with [0-9] for \d
        $preRelease = '(?:0|[1-9][0-9]*|[0-9]*[a-zA-Z-][0-9a-zA-Z-]*)';
        $re = '/^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)'
            . '(?:-' . $preRelease . '(?:\.' . $preRelease . ')*)?'
            . '(?:\+[0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*)?\z/';

        return self::buildRule($re, 'WRONG_SEMVER');
    }

    public static function jwt()
    {
        Arguments::checkNone(func_get_args(), 'jwt');

        // header.payload.signature in base64url without padding, the signature is empty in an
        // unsecured JWT. No segment has a length of 1 modulo 4: no bytes give such a length
        $segment = '(?:[A-Za-z0-9_-]{4})*(?:[A-Za-z0-9_-]{2,3})?';
        $re = '/^(?=[^.])' . $segment . '\.(?=[^.])' . $segment . '\.' . $segment . '\z/';

        return self::buildRule($re, 'WRONG_JWT');
    }

    /**
     * A rule that checks the string form of a value with a pattern and outputs it
     */
    private static function buildRule($re, $errorCode)
    {
        return function ($value, $params, &$outputArr) use ($re, $errorCode) {
            if (is_string($value)) {
                if ($value === '') {
                    return;
                }

                return preg_match($re, $value) ? null : $errorCode;
            }

            $str = self::toFormatString($value, $errorCode, $error);
            if ($str === null) {
                return $error;
            }

            if (!preg_match($re, $str)) {
                return $errorCode;
            }

            $outputArr = $str;
        };
    }

    /**
     * The string form of a value for a format or a checksum rule, or null with the error in
     * $error: none for an empty value, the code of the rule for a boolean, FORMAT_ERROR for an
     * object or a list
     */
    public static function toFormatString($value, $errorCode, &$error)
    {
        if (is_string($value)) {
            $error = null;
            return $value === '' ? null : $value;
        }

        if (is_int($value) || is_float($value)) {
            return Values::stringForm($value);
        }

        if ($value === null) {
            $error = null;
        } else {
            $error = is_bool($value) ? $errorCode : 'FORMAT_ERROR';
        }

        return null;
    }

    // "v4" or "v6" as 4 or 6, 0 for both
    private static function ipVersion($args, $ruleName)
    {
        $args = Arguments::get($args);

        if (!$args) {
            return 0;
        }

        // The arguments after the version are ignored. 4 is "4", not "v4"
        if ($args[0] === 'v4' || $args[0] === 'v6') {
            return $args[0] === 'v4' ? 4 : 6;
        }

        throw new \Exception("LIVR: \"$ruleName\" takes no arguments, \"v4\" or \"v6\"");
    }

    private static function isIp($str, $version)
    {
        return ($version !== 6 && preg_match(self::IPV4, $str)) || ($version !== 4 && self::isIPv6($str));
    }

    /**
     * RFC 4291, section 2.2: eight groups, or fewer with one "::" for one or more groups of zeros
     */
    private static function isIPv6($str)
    {
        $gapIndex = strpos($str, '::');

        if ($gapIndex === false) {
            return self::countIPv6Groups($str, true) === 8;
        }

        // A second "::" (":::" too) is ambiguous
        if (strpos($str, '::', $gapIndex + 1) !== false) {
            return false;
        }

        $before = self::countIPv6Groups(substr($str, 0, $gapIndex), false);
        $after  = self::countIPv6Groups((string) substr($str, $gapIndex + 2), true);

        return $before !== -1 && $after !== -1 && $before + $after <= 7;
    }

    /**
     * The number of 16-bit groups: hex groups separated by ":", the last one may be an IPv4
     * address (two groups) at the end of the address. -1 for a wrong group
     */
    private static function countIPv6Groups($str, $isAddressEnd)
    {
        if ($str === '') {
            return 0;
        }

        $groups = explode(':', $str);
        $last = array_pop($groups);

        foreach ($groups as $group) {
            if (!self::isHexGroup($group)) {
                return -1;
            }
        }

        if (self::isHexGroup($last)) {
            return count($groups) + 1;
        }

        if ($isAddressEnd && preg_match(self::IPV4, $last)) {
            return count($groups) + 2;
        }

        return -1;
    }

    private static function isHexGroup($group)
    {
        $length = strlen($group);

        return $length >= 1 && $length <= 4 && strspn($group, '0123456789abcdefABCDEF') === $length;
    }
}
