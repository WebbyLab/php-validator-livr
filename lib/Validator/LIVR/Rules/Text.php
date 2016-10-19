<?php

namespace Validator\LIVR\Rules;

use Validator\LIVR\Util;

/**
 * Class Text
 * @package Validator\LIVR\Rules
 */
class Text
{
	const UNICODE_SUPPORT = 'u';

	/**
	 * @return \Closure
	 */
	public static function oneOf()
	{
		$first_arg = func_get_arg(0);

		if (is_array($first_arg) && !Util::isAssocArray($first_arg))
		{
			$allowedValues = $first_arg;
		}
		else
		{
			$allowedValues = func_get_args();
			array_pop($allowedValues); # pop rule_builders
		}

		$modifiedAllowedValues = [];
		foreach ($allowedValues as $v)
		{
			$modifiedAllowedValues[] = (string)$v;
		}

		return function ($value) use ($modifiedAllowedValues)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (!in_array((string)$value, $modifiedAllowedValues, true))
			{
				return 'NOT_ALLOWED_VALUE';
			}

			return;
		};
	}


	/**
	 * @param $maxLength
	 *
	 * @return \Closure
	 */
	public static function maxLength($maxLength)
	{
		return function ($value) use ($maxLength)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (mb_strlen($value, "UTF-8") > $maxLength)
			{
				return 'TOO_LONG';
			}

			return;
		};
	}


	/**
	 * @param $minLength
	 *
	 * @return \Closure
	 */
	public static function minLength($minLength)
	{
		return function ($value) use ($minLength)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (mb_strlen($value, "UTF-8") < $minLength)
			{
				return 'TOO_SHORT';
			}

			return;
		};
	}


	/**
	 * @param $length
	 *
	 * @return \Closure
	 */
	public static function lengthEqual($length)
	{
		return function ($value) use ($length)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (mb_strlen($value, "UTF-8") < $length)
			{
				return 'TOO_SHORT';
			}

			if (mb_strlen($value, "UTF-8") > $length)
			{
				return 'TOO_LONG';
			}

			return;
		};
	}

	/**
	 * @param $minLength
	 * @param $maxLength
	 *
	 * @return \Closure
	 */
	public static function lengthBetween($minLength, $maxLength)
	{
		return function ($value) use ($minLength, $maxLength)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (mb_strlen($value, "UTF-8") < $minLength)
			{
				return 'TOO_SHORT';
			}

			if (mb_strlen($value, "UTF-8") > $maxLength)
			{
				return 'TOO_LONG';
			}

			return;
		};
	}


	/**
	 * @param $re
	 *
	 * @return \Closure
	 */
	public static function like($re)
	{
		$re = '/' . $re . '/';

		if (func_num_args() == 3)
		{
			#Passed regexp flag
			$flags = func_get_arg(1);

			if ($flags && $flags != 'i')
			{
				throw new \Exception("Only 'i' regexp flag supported, but '" . $flags . "' passed");
			}

			$re .= $flags;
		};

		$re .= self::UNICODE_SUPPORT;

		return function ($value) use ($re)
		{
			if (!isset($value) or $value === '')
			{
				return;
			}

			if (!Util::isStringOrNumber($value))
			{
				return 'FORMAT_ERROR';
			}

			if (!preg_match($re, $value))
			{
				return 'WRONG_FORMAT';
			}

			return;
		};
	}
}
