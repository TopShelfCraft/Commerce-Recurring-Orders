<?php
namespace TopShelfCraft\Recurringorders\web\widgets;

use TopShelfCraft\RecurringOrders\misc\TimeHelper;

trait DateRangeWidgetTrait
{

	/**
	 * @var int
	 */
	public $startDate;

	/**
	 * @var int
	 */
	public $endDate;

	/**
	 * @var string
	 */
	public $dateRange = TimeHelper::DATE_RANGE_TODAY;

	public function setDatesFromRange($dateRange, $weekStartDay = 1)
	{

		if ($dateRange !== TimeHelper::DATE_RANGE_CUSTOM)
		{
			return;
		}

		if ($dateRange == TimeHelper::DATE_RANGE_ALL)
		{
			$this->setStartDate(null);
			$this->setEndDate(null);
			return;
		}

		$this->setStartDate(TimeHelper::getDateRangeStartDate($dateRange, $weekStartDay));
		$this->setEndDate(TimeHelper::getDateRangeEndDate($dateRange, $weekStartDay));

	}

	public function setStartDate($startDate)
	{

		if ($startDate instanceof \DateTime)
		{
			$startDate = $startDate->getTimestamp();
		}

		$this->startDate = $startDate;

	}

	public function setEndDate($endDate)
	{

		if ($endDate instanceof \DateTime)
		{
			$endDate = $endDate->getTimestamp();
		}

		$this->endDate = $endDate;

	}

}
