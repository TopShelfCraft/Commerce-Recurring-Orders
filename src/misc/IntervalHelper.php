<?php
namespace topshelfcraft\recurringorders\misc;

use craft\helpers\ConfigHelper;
use craft\helpers\StringHelper;
use yii\base\Exception;

class IntervalHelper
{

	/**
	 * Normalizes a time duration value into a DateInterval
	 *
	 * Accepted formats:
	 * - non-zero integer (the duration in seconds)
	 * - a string in [duration interval format](https://en.wikipedia.org/wiki/ISO_8601#Durations)
	 * - a string in [relative date/time format](https://www.php.net/manual/en/datetime.formats.relative.php)
	 * - DateInterval object
	 *
	 * @param \DateInterval|string|int $interval
	 *
	 * @return \DateInterval
	 *
	 * @throws \Exception if the interval cannot be parsed.
	 */
	public static function normalizeInterval($interval)
	{

		if (empty($interval))
		{
			// TODO: Translate.
			throw new Exception("Unable to convert empty value to date interval.");
		}

		if ($interval instanceof \DateInterval)
		{
			return $interval;
		}

		if (is_numeric($interval))
		{
			$interval = "PT" . intval($interval) . "S";
		}

		if (is_string($interval) && StringHelper::startsWith($interval, 'P'))
		{
			return new \DateInterval($interval);
		}

		if (is_string($interval))
		{
			return \DateInterval::createFromDateString($interval);
		}

		// TODO: Translate.
		throw new Exception("Unable to convert {$interval} to date interval.");

	}

	/**
	 * Normalizes a time duration value into the number of seconds it represents.
	 *
	 * @param \DateInterval|string|int $interval
	 *
	 * @return int The time duration, in seconds
	 *
	 * @throws \Exception if the interval cannot be parsed.
	 *
	 * @todo Remove this if Craft's `ConfigHelper::durationInSeconds()` ever supports ISO 8601 strings.
	 */
	public static function durationInSeconds($interval)
	{
		$interval = self::normalizeInterval($interval);
		return ConfigHelper::durationInSeconds($interval);
	}

	/**
	 * @param mixed $interval
	 *
	 * @return bool
	 */
	public static function isValidInterval($interval)
	{
		try
		{
			$interval = self::normalizeInterval($interval);
			return ($interval instanceof \DateInterval);
		}
		catch (\Exception $e)
		{
			return false;
		}
	}

}
