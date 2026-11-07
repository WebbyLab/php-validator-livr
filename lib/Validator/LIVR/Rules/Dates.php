<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Util;
use Validator\LIVR\Values;

/**
 * iso_date with bounds, iso_datetime and iso_time (LIVR 2.5, 3.3). The bounds "today", "yesterday"
 * and "tomorrow" are the current date in UTC, one day before it and one day after it. The current
 * time is time(), called without a namespace, so tests can replace it in this namespace
 * (php-mock). It is read for every value with a relative bound.
 *
 * Instants are compared as keys, integers in the order of time without the days-from-civil
 * arithmetic: (year * 512 + month * 32 + day) * DAY_MS + milliseconds of the day, all in UTC
 */
class Dates
{
    const DAY_MS = 86400000;

    // \z, not $: "$" also matches before a trailing newline. [0-9], not \d
    const DATE_RE = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})\z/';
    const DATETIME_RE = '/^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2})(?::([0-9]{2})(?:\.([0-9]{1,9}))?)?'
        . '(?:Z|([+-])([0-9]{2}):([0-9]{2}))\z/';
    const TIME_RE = '/^([0-9]{2}):([0-9]{2})(?::([0-9]{2})(?:\.([0-9]{1,9}))?)?\z/';

    private static $RELATIVE_DAYS = array('yesterday' => -1, 'today' => 0, 'tomorrow' => 1);

    // The day number (days since 1970-01-01) of the cached relative dates: they change once a
    // day, and a validator created for every validation gets them without calendar arithmetic
    private static $cachedDay = null;
    private static $cachedDates = array();
    private static $cachedKeys = array();

    // The UTC fields of the last datetime that parseDatetime() read
    private static $utcYear;
    private static $utcMonth;
    private static $utcDay;
    private static $utcMsOfDay;

    /**
     * iso_date({ min, max }), called by Special::isoDate() for an options object
     */
    public static function isoDateWithBounds($options)
    {
        self::checkOptions($options, 'iso_date', 'use "iso_datetime" for datetimes');

        $min = array_key_exists('min', $options) ? self::parseDateBound($options['min'], 'min') : null;
        $max = array_key_exists('max', $options) ? self::parseDateBound($options['max'], 'max') : null;

        // Fixed bounds are dates YYYY-MM-DD, compared as strings; relative ones are days from the
        // current date. A fixed and a relative bound are not compared: that depends on the day
        if ($min !== null && $max !== null && (is_int($min) ? is_int($max) && $min > $max
            : is_string($max) && strcmp($min, $max) > 0)
        ) {
            throw new \Exception('LIVR: "iso_date" got "min" later than "max"');
        }

        $isRelative = is_int($min) || is_int($max);

        return function ($value) use ($min, $max, $isRelative) {
            if (!is_string($value)) {
                return self::notStringError($value, 'WRONG_DATE');
            }

            if ($value === '') {
                return;
            }

            if (!self::isDate($value)) {
                return 'WRONG_DATE';
            }

            $today = $isRelative ? self::today() : 0;

            if ($min !== null && strcmp($value, is_int($min) ? self::relativeDate($today, $min) : $min) < 0) {
                return 'DATE_TOO_EARLY';
            }

            if ($max !== null && strcmp($value, is_int($max) ? self::relativeDate($today, $max) : $max) > 0) {
                return 'DATE_TOO_LATE';
            }
        };
    }

    public static function isoDatetime()
    {
        $options = self::getOptions(func_get_args(), 'iso_datetime');

        $min = array_key_exists('min', $options) ? self::parseDatetimeBound($options['min'], false) : null;
        $max = array_key_exists('max', $options) ? self::parseDatetimeBound($options['max'], true) : null;

        // Fixed bounds are keys (cut to milliseconds), relative ones are [days] from the current
        // date. A fixed and a relative bound are not compared: that depends on the day
        if ($min !== null && $max !== null && (is_int($min) ? is_int($max) && $min > $max
            : is_array($max) && $min[0] > $max[0])
        ) {
            throw new \Exception('LIVR: "iso_datetime" got "min" later than "max"');
        }

        $isRelative = is_array($min) || is_array($max);

        return function ($value, $params, &$outputArr) use ($min, $max, $isRelative) {
            if (!is_string($value)) {
                return self::notStringError($value, 'WRONG_DATETIME');
            }

            if ($value === '') {
                return;
            }

            // An instant outside the years 0000-9999 in UTC cannot be written in the output form
            if (!self::parseDatetime($value) || self::$utcYear < 0 || self::$utcYear > 9999) {
                return 'WRONG_DATETIME';
            }

            if ($min !== null || $max !== null) {
                $key = self::lastKey();
                $today = $isRelative ? self::today() : 0;
                $lastMs = self::DAY_MS - 1;

                // A relative bound is the start of the day as "min" and its end as "max"
                if ($min !== null && $key < (is_array($min) ? self::relativeKey($today, $min[0]) : $min)) {
                    return 'DATETIME_TOO_EARLY';
                }

                if ($max !== null && $key > (is_array($max) ? self::relativeKey($today, $max[0]) + $lastMs : $max)) {
                    return 'DATETIME_TOO_LATE';
                }
            }

            // The instant in UTC with three fraction digits
            $msOfDay = self::$utcMsOfDay;
            $outputArr = sprintf(
                '%04d-%02d-%02dT%02d:%02d:%02d.%03dZ',
                self::$utcYear,
                self::$utcMonth,
                self::$utcDay,
                intdiv($msOfDay, 3600000),
                intdiv($msOfDay, 60000) % 60,
                intdiv($msOfDay, 1000) % 60,
                $msOfDay % 1000
            );
        };
    }

    /**
     * "iso_time", or { iso_time: { min, max } }. With min greater than max the range goes over
     * midnight: { min: "22:00", max: "06:00" } accepts 23:00 and 05:00
     */
    public static function isoTime()
    {
        $options = self::getOptions(func_get_args(), 'iso_time');

        $min = array_key_exists('min', $options) ? self::parseTimeBound($options['min'], 'min') : null;
        $max = array_key_exists('max', $options) ? self::parseTimeBound($options['max'], 'max') : null;
        $isOverMidnight = $min !== null && $max !== null && $min > $max;

        return function ($value) use ($min, $max, $isOverMidnight) {
            if (!is_string($value)) {
                return self::notStringError($value, 'WRONG_TIME');
            }

            if ($value === '') {
                return;
            }

            $time = self::parseTime($value);
            if ($time < 0) {
                return 'WRONG_TIME';
            }

            if ($isOverMidnight) {
                if ($time >= $min || $time <= $max) {
                    return;
                }

                // The gap between max and min gets the error of the nearer bound, TIME_TOO_EARLY
                // exactly in the middle
                return $time - $max < $min - $time ? 'TIME_TOO_LATE' : 'TIME_TOO_EARLY';
            }

            if ($min !== null && $time < $min) {
                return 'TIME_TOO_EARLY';
            }

            if ($max !== null && $time > $max) {
                return 'TIME_TOO_LATE';
            }
        };
    }

    /**
     * The options object of iso_datetime and iso_time from the arguments of the rule builder: the
     * first argument, if any, must be an object. The arguments after it are ignored
     */
    private static function getOptions($args, $ruleName)
    {
        $args = \Validator\LIVR\Arguments::get($args);

        if (!$args) {
            return array();
        }

        // An empty array is {}: PHP decodes {} and [] to the same array
        if (!is_array($args[0])) {
            throw new \Exception("LIVR: \"$ruleName\" takes no arguments or an object with \"min\" and \"max\"");
        }

        self::checkOptions($args[0], $ruleName, null);

        return $args[0];
    }

    private static function checkOptions($options, $ruleName, $formatHint)
    {
        if ($options && !Util::isAssocArray($options)) {
            throw new \Exception("LIVR: \"$ruleName\" takes no arguments or an object with \"min\" and \"max\"");
        }

        foreach ($options as $key => $option) {
            if ($key !== 'min' && $key !== 'max') {
                $hint = $key === 'format' && $formatHint ? ", $formatHint" : '';
                throw new \Exception("LIVR: \"$ruleName\" got an unknown option \"$key\"$hint");
            }
        }
    }

    // A date bound: a date YYYY-MM-DD, or the days from the current date as an integer
    private static function parseDateBound($bound, $name)
    {
        if (is_string($bound)) {
            if (isset(self::$RELATIVE_DAYS[$bound])) {
                return self::$RELATIVE_DAYS[$bound];
            }

            if (self::isDate($bound)) {
                return $bound;
            }
        }

        throw new \Exception(
            "LIVR: \"iso_date\" takes a date YYYY-MM-DD, \"today\", \"yesterday\" or \"tomorrow\" as \"$name\", got "
            . json_encode($bound) . ($bound === 'current' ? ', use "today" instead of "current"' : '')
        );
    }

    // A datetime bound: the key of an instant, or [days] from the current date. A date, given or
    // relative, is the start of the UTC day as "min" and its end as "max"
    private static function parseDatetimeBound($bound, $isMax)
    {
        if (is_string($bound)) {
            if (isset(self::$RELATIVE_DAYS[$bound])) {
                return array(self::$RELATIVE_DAYS[$bound]);
            }

            if (strlen($bound) === 10) {
                if (self::parseDatetime($bound . 'T00:00Z')) {
                    return $isMax ? self::lastKey() + self::DAY_MS - 1 : self::lastKey();
                }
            } elseif (self::parseDatetime($bound)) {
                // An instant of any year: it is only compared with values
                return self::lastKey();
            }
        }

        throw new \Exception(
            'LIVR: "iso_datetime" takes a datetime with an offset, a date YYYY-MM-DD, "today", "yesterday" or '
            . '"tomorrow" as "' . ($isMax ? 'max' : 'min') . '", got ' . json_encode($bound)
            . ($bound === 'current' ? ', use "today" instead of "current"' : '')
        );
    }

    private static function parseTimeBound($bound, $name)
    {
        $time = is_string($bound) ? self::parseTime($bound) : -1;

        if ($time < 0) {
            throw new \Exception("LIVR: \"iso_time\" got a wrong time in \"$name\": " . json_encode($bound));
        }

        return $time;
    }

    private static function isDate($str)
    {
        if (!preg_match(self::DATE_RE, $str, $m)) {
            return false;
        }

        // checkdate() starts with year 1. Year 0 is a leap year, like 2000
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1] ?: 2000);
    }

    /**
     * YYYY-MM-DD "T" HH ":" MM [ ":" SS [ "." 1*9DIGIT ] ] ( "Z" / ( "+" / "-" ) HH ":" MM ).
     * Sets the UTC fields of the instant, cut to milliseconds. False when it is not such a datetime
     */
    private static function parseDatetime($str)
    {
        if (!preg_match(self::DATETIME_RE, $str, $m)) {
            return false;
        }

        $year    = (int) $m[1];
        $month   = (int) $m[2];
        $day     = (int) $m[3];
        $hours   = (int) $m[4];
        $minutes = (int) $m[5];
        $seconds = isset($m[6]) ? (int) $m[6] : 0;

        if ($hours > 23 || $minutes > 59 || $seconds > 59 || !checkdate($month, $day, $year ?: 2000)) {
            return false;
        }

        // The first three fraction digits, the others are cut off
        $ms = isset($m[7]) && $m[7] !== '' ? (int) str_pad(substr($m[7], 0, 3), 3, '0') : 0;

        // Minutes east of UTC
        $offset = 0;
        if (isset($m[8]) && $m[8] !== '') {
            $offsetHours = (int) $m[9];
            $offsetMinutes = (int) $m[10];

            if ($offsetHours > 23 || $offsetMinutes > 59) {
                return false;
            }

            $offset = $m[8] === '-' ? -($offsetHours * 60 + $offsetMinutes) : $offsetHours * 60 + $offsetMinutes;
        }

        // An offset moves the time by less than a day, so at most to the next or the previous day
        $minutesOfDay = $hours * 60 + $minutes - $offset;

        if ($minutesOfDay < 0) {
            $minutesOfDay += 1440;

            if (--$day === 0) {
                if (--$month === 0) {
                    $month = 12;
                    $year--;
                }

                $day = self::daysInMonth($year, $month);
            }
        } elseif ($minutesOfDay >= 1440) {
            $minutesOfDay -= 1440;

            if (++$day > 28 && $day > self::daysInMonth($year, $month)) {
                $day = 1;

                if (++$month === 13) {
                    $month = 1;
                    $year++;
                }
            }
        }

        self::$utcYear    = $year;
        self::$utcMonth   = $month;
        self::$utcDay     = $day;
        self::$utcMsOfDay = ($minutesOfDay * 60 + $seconds) * 1000 + $ms;

        return true;
    }

    // The key of the last parsed datetime
    private static function lastKey()
    {
        return (self::$utcYear * 512 + self::$utcMonth * 32 + self::$utcDay) * self::DAY_MS + self::$utcMsOfDay;
    }

    /**
     * HH:MM, HH:MM:SS or HH:MM:SS.fraction (1-9 digits): nanoseconds since midnight, exact in a
     * 64-bit integer, so "09:00" equals "09:00:00.0". -1 for a wrong time
     */
    private static function parseTime($str)
    {
        if (!preg_match(self::TIME_RE, $str, $m)) {
            return -1;
        }

        $hours   = (int) $m[1];
        $minutes = (int) $m[2];
        $seconds = isset($m[3]) ? (int) $m[3] : 0;

        if ($hours > 23 || $minutes > 59 || $seconds > 59) {
            return -1;
        }

        $fraction = isset($m[4]) ? (int) str_pad($m[4], 9, '0') : 0;

        return (($hours * 60 + $minutes) * 60 + $seconds) * 1000000000 + $fraction;
    }

    // A value that is not a string: empty, a number or a boolean (never a date), or not primitive
    private static function notStringError($value, $errorCode)
    {
        if ($value === null) {
            return;
        }

        return Values::isPrimitiveValue($value) ? $errorCode : 'FORMAT_ERROR';
    }

    // The current date in UTC as a day number, never in the local time zone. time() without a
    // namespace: tests replace it in this namespace
    private static function today()
    {
        return (int) floor(time() / 86400);
    }

    // The date YYYY-MM-DD of $days from the day number $today
    private static function relativeDate($today, $days)
    {
        if ($today !== self::$cachedDay) {
            self::cacheDay($today);
        }

        return self::$cachedDates[$days];
    }

    // The key of the start of the day $days from the day number $today
    private static function relativeKey($today, $days)
    {
        if ($today !== self::$cachedDay) {
            self::cacheDay($today);
        }

        return self::$cachedKeys[$days];
    }

    private static function cacheDay($today)
    {
        foreach (self::$RELATIVE_DAYS as $days) {
            list($year, $month, $day) = self::civilFromDays($today + $days);
            self::$cachedDates[$days] = sprintf('%04d-%02d-%02d', $year, $month, $day);
            self::$cachedKeys[$days] = ($year * 512 + $month * 32 + $day) * self::DAY_MS;
        }

        self::$cachedDay = $today;
    }

    private static function daysInMonth($year, $month)
    {
        if ($month === 2) {
            return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0 ? 29 : 28;
        }

        return $month === 4 || $month === 6 || $month === 9 || $month === 11 ? 30 : 31;
    }

    // The date of a day number, "civil_from_days" by Howard Hinnant
    private static function civilFromDays($days)
    {
        $z = $days + 719468;
        $era = intdiv($z >= 0 ? $z : $z - 146096, 146097);
        $dayOfEra = $z - $era * 146097;
        $yearOfEra = intdiv(
            $dayOfEra - intdiv($dayOfEra, 1460) + intdiv($dayOfEra, 36524) - intdiv($dayOfEra, 146096),
            365
        );
        $dayOfYear = $dayOfEra - (365 * $yearOfEra + intdiv($yearOfEra, 4) - intdiv($yearOfEra, 100));
        $shiftedMonth = intdiv(5 * $dayOfYear + 2, 153); // March is 0
        $day = $dayOfYear - intdiv(153 * $shiftedMonth + 2, 5) + 1;
        $month = $shiftedMonth < 10 ? $shiftedMonth + 3 : $shiftedMonth - 9;

        return array($yearOfEra + $era * 400 + ($month <= 2 ? 1 : 0), $month, $day);
    }
}
