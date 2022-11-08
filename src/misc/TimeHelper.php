<?php
namespace TopShelfCraft\RecurringOrders\misc;

use Craft;
use craft\helpers\ConfigHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use craft\i18n\Locale;
use yii\base\Exception;

class TimeHelper
{

	const DATE_RANGE_ALL = 'all';
	const DATE_RANGE_TODAY = 'today';
	const DATE_RANGE_THISWEEK = 'thisWeek';
	const DATE_RANGE_THISMONTH = 'thisMonth';
	const DATE_RANGE_THISYEAR = 'thisYear';
	const DATE_RANGE_PAST7DAYS = 'past7Days';
	const DATE_RANGE_PAST30DAYS = 'past30Days';
	const DATE_RANGE_PAST90DAYS = 'past90Days';
	const DATE_RANGE_PASTYEAR = 'pastYear';
	const DATE_RANGE_CUSTOM = 'custom';

	const DATE_RANGE_INTERVAL = [
		self::DATE_RANGE_TODAY => 'day',
		self::DATE_RANGE_THISWEEK => 'day',
		self::DATE_RANGE_THISMONTH => 'day',
		self::DATE_RANGE_THISYEAR => 'month',
		self::DATE_RANGE_PAST7DAYS => 'day',
		self::DATE_RANGE_PAST30DAYS => 'day',
		self::DATE_RANGE_PAST90DAYS => 'day',
		self::DATE_RANGE_PASTYEAR => 'month',
		self::DATE_RANGE_ALL => 'month',
	];

	const START_DAY_INT_TO_DAY = [
		0 => 'Sunday',
		1 => 'Monday',
		2 => 'Tuesday',
		3 => 'Wednesday',
		4 => 'Thursday',
		5 => 'Friday',
		6 => 'Saturday',
	];

	const START_DAY_INT_TO_END_DAY = [
		0 => 'Saturday',
		1 => 'Sunday',
		2 => 'Monday',
		3 => 'Tuesday',
		4 => 'Wednesday',
		5 => 'Thursday',
		6 => 'Friday',
	];

	/**
	 * @return \DateTime
	 */
	public static function now()
	{
		return new \DateTime();
	}

	/**
	 * @param $interval
	 *
	 * @return \DateTime
	 *
	 * @throws \Exception
	 */
	public static function fromNow($interval)
	{
		$interval = static::normalizeInterval($interval);
		return static::now()->add($interval);
	}

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

	public static function getDateRangeWording($dateRange, $startDate, $endDate): string
	{

		switch ($dateRange) {

			case self::DATE_RANGE_ALL:
			{
				return Craft::t('commerce', 'All');
			}

			case self::DATE_RANGE_TODAY:
			{
				return Craft::t('commerce', 'Today');
			}

			case self::DATE_RANGE_THISWEEK:
			{
				return Craft::t('commerce', 'This week');
			}

			case self::DATE_RANGE_THISMONTH:
			{
				return Craft::t('commerce', 'This month');
			}

			case self::DATE_RANGE_THISYEAR:
			{
				return Craft::t('commerce', 'This year');
			}

			case self::DATE_RANGE_PAST7DAYS:
			{
				return Craft::t('commerce', 'Past {num} days', ['num' => 7]);
			}

			case self::DATE_RANGE_PAST30DAYS:
			{
				return Craft::t('commerce', 'Past {num} days', ['num' => 30]);
			}

			case self::DATE_RANGE_PAST90DAYS:
			{
				return Craft::t('commerce', 'Past {num} days', ['num' => 90]);
			}

			case self::DATE_RANGE_PASTYEAR:
			{
				return Craft::t('commerce', 'Past year');
			}

			case self::DATE_RANGE_CUSTOM:
			{

				if (!$startDate || !$endDate) {
					return '';
				}

				$startDate = Craft::$app->getFormatter()->asDate($startDate, Locale::LENGTH_SHORT);
				$endDate = Craft::$app->getFormatter()->asDate($endDate, Locale::LENGTH_SHORT);

				if (Craft::$app->getLocale()->getOrientation() == 'rtl') {
					return $endDate . ' - ' . $startDate;
				}

				return $startDate . ' - ' . $endDate;

			}

		}

		return '';

	}

	/**
	 * Calculate the Start Date for a named range.
	 */
	public static function getDateRangeStartDate(string $dateRange, int $weekStartDay = 1): ?\DateTime
	{

		if ($dateRange === self::DATE_RANGE_CUSTOM) {
			return null;
		}

		$date = new \DateTime();

		switch ($dateRange) {

			case self::DATE_RANGE_ALL:
			{
				return null;
			}

			case self::DATE_RANGE_THISMONTH:
			{
				$date = DateTimeHelper::toDateTime(strtotime('first day of this month'));
				break;
			}

			case self::DATE_RANGE_THISWEEK:
			{
				if (date('l') != self::START_DAY_INT_TO_DAY[$weekStartDay]) {
					$date = DateTimeHelper::toDateTime(strtotime('last ' . self::START_DAY_INT_TO_DAY[$weekStartDay]));
				}
				break;
			}

			case self::DATE_RANGE_THISYEAR:
			{
				$date->setDate($date->format('Y'), 1, 1);
				break;
			}

			case self::DATE_RANGE_PAST7DAYS:
			case self::DATE_RANGE_PAST30DAYS:
			case self::DATE_RANGE_PAST90DAYS:
			{
				$number = str_replace(['past', 'Days'], '', $dateRange);
				// Minus one so we include today as a "past day"
				$number--;
				$date = self::getDateRangeEndDate($dateRange, $weekStartDay);
				$interval = new \DateInterval('P' . $number . 'D');
				$date->sub($interval);
				break;
			}

			case self::DATE_RANGE_PASTYEAR:
			{
				$date = self::getDateRangeEndDate($dateRange, $weekStartDay);
				$interval = new \DateInterval('P1Y');
				$date->sub($interval);
				$date->add(new \DateInterval('P1M'));
				break;
			}

		}

		$date->setTime(0, 0);

		return $date;

	}

	/**
	 * Calculate the End Date for a named range.
	 */
	public static function getDateRangeEndDate(string $dateRange, int $weekStartDay = 1): ?\DateTime
	{

		if ($dateRange === self::DATE_RANGE_CUSTOM) {
			return null;
		}

		$date = new \DateTime();

		switch ($dateRange) {

			case self::DATE_RANGE_THISMONTH:
			{
				$date = DateTimeHelper::toDateTime(strtotime('last day of this month'));
				break;
			}

			case self::DATE_RANGE_THISWEEK:
			{
				$endDayOfWeek = self::START_DAY_INT_TO_END_DAY[$weekStartDay];
				if (date('l') != $endDayOfWeek) {
					$date = DateTimeHelper::toDateTime(strtotime('next ' . $endDayOfWeek));
				}
				break;
			}

		}

		$date->setTime(23, 59, 59);

		return $date;

	}

}
