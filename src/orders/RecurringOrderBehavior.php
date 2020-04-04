<?php
namespace topshelfcraft\recurringorders\orders;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\DateTimeHelper;
use topshelfcraft\recurringorders\misc\IntervalHelper;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\Behavior;

/**
 * @property string|null $recurrenceStatus
 * @property string|null $recurrenceInterval
 * @property string|null $recurrenceErrorReason
 * @property int|null $recurrenceErrorCount
 * @property \DateTime|null $lastRecurrence
 * @property \DateTime|null $nextRecurrence
 * @property string|null $originatingOrderId
 * @property string|null $parentOrderId
 * @property bool $resetNextRecurrenceOnSave
 *
 * @property Order $owner
 */
class RecurringOrderBehavior extends Behavior
{

	/**
	 * @var RecurringOrderRecord
	 */
	private $_record;

	/**
	 * @var bool
	 */
	private $_resetNextRecurrenceOnSave;

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [];
	}

	/**
	 * @param RecurringOrderRecord|null $record
	 */
	public function loadRecurringOrderRecord(RecurringOrderRecord $record = null)
	{
		$this->_record = $record ?: RecurringOrderRecord::findOne($this->owner->id);
	}

	/**
	 * @return RecurringOrderRecord|null
	 */
	private function _getRecord()
	{
		if (!isset($this->_record))
		{
			$this->loadRecurringOrderRecord();
		}
		return $this->_record;
	}

	/**
	 * @return RecurringOrderRecord
	 */
	private function _getOrMakeRecord()
	{
		$record = $this->_getRecord();
		if (!$record)
		{
			$this->_record = new RecurringOrderRecord([
				'id' => $this->owner->id,
			]);
		}
		return $this->_record;
	}


	/**
	 * Whether the Order is managed by the Recurring Orders plugin.
	 *
	 * @return bool
	 */
	public function getIsRecurring()
	{
		return $this->_getRecord() ? !empty($this->_getRecord()->status) : false;
	}

	/**
	 * Whether the Order has both a Recurrence Interval and Next recurrence set.
	 *
	 * @return bool
	 */
	public function hasRecurrenceSchedule()
	{
		return !empty($this->_getRecord()->recurrenceInterval) && !empty($this->_getRecord()->nextRecurrence);
	}

	/**
	 * @return string|null
	 */
	public function getRecurrenceStatus()
	{

		if (!$this->_getRecord())
		{
			return null;
		}

		/*
		 * A record may have an "Active" status, but its recurrence schedule isn't set yet,
		 * in which case it gets the special implicit status of "Unscheduled"
		 */
		if ($this->_getRecord()->status === RecurringOrderRecord::STATUS_ACTIVE)
		{
			if (!$this->hasRecurrenceSchedule())
			{
				return RecurringOrderRecord::STATUS_UNSCHEDULED;
			}
		}

		return $this->_getRecord()->status;

	}

	/**
	 * @param $value
	 */
	public function setRecurrenceStatus($value)
	{
		// TODO: Validate immediately?
		$this->_getOrMakeRecord()->status = $value;
	}

	/**
	 * @return string|null
	 */
	public function getRecurrenceInterval()
	{
		return $this->_getRecord() ? $this->_getRecord()->recurrenceInterval : null;
	}

	/**
	 * @param $value
	 */
	public function setRecurrenceInterval($value)
	{
		$this->_getOrMakeRecord()->recurrenceInterval = $value;
	}

	/**
	 * @return string|null
	 */
	public function getHumanReadableRecurrenceInterval()
	{
		if ($this->getRecurrenceInterval())
		{
			try
			{
				return DateTimeHelper::humanDurationFromInterval(
					IntervalHelper::normalizeInterval($this->getRecurrenceInterval())
				);
			}
			catch (\Exception $e)
			{
				RecurringOrders::error($e->getMessage());
			}
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getRecurrenceErrorReason()
	{
		return $this->_getRecord() ? $this->_getRecord()->errorReason : null;
	}

	/**
	 * @param $value
	 */
	public function setRecurrenceErrorReason($value)
	{
		$this->_getOrMakeRecord()->errorReason = $value;
	}

	/**
	 * @return int|null
	 */
	public function getRecurrenceErrorCount()
	{
		return $this->_getRecord() ? $this->_getRecord()->errorCount : null;
	}

	/**
	 * @param $value
	 */
	public function setRecurrenceErrorCount($value)
	{
		$this->_getOrMakeRecord()->errorCount = $value;
	}

	/**
	 * @return \DateTime|null
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime object.
	 */
	public function getLastRecurrence()
	{
		return $this->_getRecord() ? $this->_getRecord()->lastRecurrence : null;
	}

	/**
	 * @param $value
	 *
	 * @internal
	 */
	public function setLastRecurrence($value)
	{
		$this->_getOrMakeRecord()->lastRecurrence = $value;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getNextRecurrence()
	{
		return $this->_getRecord() ? $this->_getRecord()->nextRecurrence : null;
	}

	/**
	 * @param $value
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime
	 */
	public function setNextRecurrence($value)
	{
		$this->_getOrMakeRecord()->nextRecurrence = DateTimeHelper::toDateTime($value);
	}

	/**
	 * @return int|null
	 */
	public function getOriginatingOrderId()
	{
		return $this->_getRecord() ? $this->_getRecord()->originatingOrderId : null;
	}

	/**
	 * @param $value
	 */
	public function setOriginatingOrderId($value)
	{
		$this->_getOrMakeRecord()->originatingOrderId = $value;
	}

	/**
	 * @return Order|null
	 */
	public function getOriginatingOrder()
	{
		return Commerce::getInstance()->orders->getOrderById($this->getOriginatingOrderId());
	}

	/**
	 * @return int|null
	 */
	public function getParentOrderId()
	{
		return $this->_getRecord() ? $this->_getRecord()->parentOrderId : null;
	}

	/**
	 * @param $value
	 */
	public function setParentOrderId($value)
	{
		$this->_getOrMakeRecord()->parentOrderId = $value;
	}

	/**
	 * @return Order|null
	 */
	public function getParentOrder()
	{
		return Commerce::getInstance()->orders->getOrderById($this->getParentOrderId());
	}

	/**
	 * @return bool
	 */
	public function getResetNextRecurrenceOnSave()
	{
		return $this->_resetNextRecurrenceOnSave;
	}

	/**
	 * @param $value
	 */
	public function setResetNextRecurrenceOnSave($value)
	{
		$this->_resetNextRecurrenceOnSave = (bool) $value;
	}

	/**
	 * @throws \Exception if there's a problem calculating the new date.
	 */
	public function resetNextRecurrence()
	{
		$newDate = (new \DateTime())->add(IntervalHelper::normalizeInterval($this->getRecurrenceInterval()));
		$this->setNextRecurrence($newDate);
	}

	/**
	 * @param array|null $names
	 * @param array $except
	 *
	 * @return array
	 */
	public function getRecurringOrdersAttributes($names = null, $except = [])
	{
		return $this->_getRecord() ? $this->_getRecord()->getAttributes($names, $except) : [];
	}

	/**
	 * @param array $attributes
	 * @param bool $safeOnly
	 */
	public function setRecurringOrdersAttributes($attributes = [], $safeOnly = false)
	{
		$this->_getOrMakeRecord()->setAttributes($attributes, $safeOnly);
	}

	/**
	 * @param array|null $attributes
	 *
	 * @return bool
	 */
	public function saveRecurringOrdersRecord($attributes = null)
	{
		$record = $this->_getOrMakeRecord();
		$record->setAttributes($attributes, false);
		return $record->save();
	}

}
