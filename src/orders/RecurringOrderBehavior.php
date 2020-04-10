<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
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
 * @property int|null $paymentSourceId
 * @property mixed $spec
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
	 * @var Spec
	 */
	private $_spec;

	/**
	 * @var bool
	 */
	private $_resetNextRecurrenceOnSave;

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
	public function hasRecurrenceStatus()
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
		// TODO: Validate?
		$this->_getOrMakeRecord()->recurrenceInterval = ($value ?: null);
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
		$this->_getOrMakeRecord()->errorReason = ($value ?: null);
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
	 */
	public function getLastRecurrence()
	{
		return $this->_getRecord() ? $this->_getRecord()->lastRecurrence : null;
	}

	/**
	 * @param $value
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime
	 *
	 * @internal
	 */
	public function setLastRecurrence($value)
	{
		// TODO: Typecasting and validation should probably go in the Record.
		$this->_getOrMakeRecord()->lastRecurrence = (DateTimeHelper::toDateTime($value) ?: null);
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
		// TODO: Typecasting and validation should probably go in the Record.
		$this->_getOrMakeRecord()->nextRecurrence = (DateTimeHelper::toDateTime($value) ?: null);
	}

	/**
	 * @return int|null
	 */
	public function getPaymentSourceId()
	{
		return $this->_getRecord() ? $this->_getRecord()->paymentSourceId : null;
	}

	/**
	 * @param $value
	 */
	public function setPaymentSourceId($value)
	{
		$this->_getOrMakeRecord()->paymentSourceId = ((int)$value ?: null);
	}

	/**
	 * @return PaymentSource|null
	 */
	public function getPaymentSource()
	{
		return $this->getPaymentSourceId()
			? Commerce::getInstance()->paymentSources->getPaymentSourceById($this->getPaymentSourceId())
			: null;
	}

	/**
	 * @return Spec
	 */
	public function getSpec()
	{
		if (!isset($this->_spec))
		{
			$config = $this->_getRecord() ? $this->_getRecord()->spec : null;
			$this->_spec = new Spec($config);
		}
		return $this->_spec;
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
		$this->_getOrMakeRecord()->originatingOrderId = ((int)$value ?: null);
	}

	/**
	 * @return Order|null
	 */
	public function getOriginatingOrder()
	{
		return $this->getOriginatingOrderId()
			? Commerce::getInstance()->orders->getOrderById($this->getOriginatingOrderId())
			: null;
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
		$this->_getOrMakeRecord()->parentOrderId = ((int)$value ?: null);
	}

	/**
	 * @return Order|null
	 */
	public function getParentOrder()
	{
		return $this->getParentOrderId()
			? Commerce::getInstance()->orders->getOrderById($this->getParentOrderId())
			: null;
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
		if (!$this->getRecurrenceInterval())
		{
			// TODO: Is failing silently the right thing to do here?
			RecurringOrders::error("Next Recurrence cannot be reset because Recurrence Interval is not defined.");
			return;
		}
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
	 *
	 * @throws \yii\db\Exception if the RecurringOrderRecord cannot be saved.
	 *
	 * @todo If the record is completely empty, perhaps we should delete it from the db to keep things tidy?
	 */
	public function saveRecurringOrdersRecord($attributes = null)
	{

		if (!$this->_record && !$this->_spec && empty($attributes))
		{
			return true;
		}

		$record = $this->_getOrMakeRecord();
		$record->spec = $this->getSpec(); // Changes to our local Spec object need to be pushed back to the Record.
		$record->setAttributes($attributes, false);
		$record->id = $this->owner->id; // In case we started earlier with a new (un-saved) Order element.

		return $record->save();

	}

}
