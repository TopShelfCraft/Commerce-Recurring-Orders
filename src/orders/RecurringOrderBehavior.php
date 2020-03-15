<?php
namespace topshelfcraft\recurringorders\orders;

use craft\commerce\elements\Order;
use craft\helpers\DateTimeHelper;
use yii\base\Behavior;

class RecurringOrderBehavior extends Behavior
{

	/**
	 * @var
	 */
	private $_recurringOrderRecord;

	/**
	 * @var bool
	 */
	private $_isRecurring;

	/**
	 * @var Order
	 */
	public $owner;

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
		$this->_recurringOrderRecord = $record ?: RecurringOrderRecord::findOne($this->owner->id);
		$this->_isRecurring = ($this->_recurringOrderRecord !== null);
	}

	/**
	 * @return bool
	 */
	public function getIsRecurring()
	{
		if ($this->_isRecurring === null)
		{
			$this->loadRecurringOrderRecord();
		}
		return $this->_isRecurring;
	}

	/**
	 * @return bool
	 */
	public function getIsScheduled()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->getIsScheduled();
		}
		return false;
	}

	/**
	 * @return RecurringOrderRecord|null
	 */
	public function getRecurringOrder()
	{
		/*
		 * Calling `getIsRecurring` implicitly forces the record to be loaded if it wasn't already.
		 * TODO: Maybe figure out a more elegant way of ensuring this...
		 */
		if ($this->getIsRecurring())
		{
			return $this->_recurringOrderRecord;
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getRecurringOrderStatus()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->getStatus();
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getRecurrenceInterval()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->recurrenceInterval;
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getHumanReadableRecurrenceInterval()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->getHumanReadableRecurrenceInterval();
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	public function getRecurringOrderErrorReason()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->errorReason;
		}
		return null;
	}

	/**
	 * @return int|null
	 */
	public function getRecurringOrderErrorCount()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->errorCount;
		}
		return null;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getLastRecurrence()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->lastRecurrence;
		}
		return null;
	}

	/**
	 * @return \DateTime|null
	 */
	public function getNextRecurrence()
	{
		if ($record = $this->getRecurringOrder())
		{
			return $record->nextRecurrence;
		}
		return null;
	}

}
