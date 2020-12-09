<?php
namespace steadfast\recurringorders\orders;

use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\events\CancelableEvent;
use craft\helpers\DateTimeHelper;
use steadfast\recurringorders\meta\RecurringOrderQuery;
use steadfast\recurringorders\misc\TimeHelper;
use steadfast\recurringorders\RecurringOrders;
use yii\base\Behavior;
use yii\base\Event;

/**
 * @property string|null $recurrenceStatus
 * @property string|null $recurrenceInterval
 * @property \DateTime|null $lastRecurrence
 * @property \DateTime|null $nextRecurrence
 * @property \DateTime|null $dateMarkedImminent
 * @property int|null $paymentSourceId
 * @property mixed $spec
 * @property string|null $originatingOrderId
 * @property string|null $parentOrderId
 * @property string|null $recurrenceErrorReason
 * @property int|null $recurrenceErrorCount
 * @property \DateTime|null $retryDate
 * @property bool $resetNextRecurrenceOnSave
 *
 * @property Order $owner
 */
class RecurringOrderBehavior extends Behavior
{

	/**
	 * @event RecurrenceStatusChangeEvent This event is raised when an Order's Recurrence Status is created or changed.
	 *
	 * ```php
	 * use craft\commerce\elements\Order;
	 * use steadfast\recurringorders\orders\RecurringOrderBehavior;
	 * use steadfast\recurringorders\orders\RecurrenceStatusChangeEvent;
	 *
	 * Event::on(Order::class, RecurringOrderBehavior::EVENT_RECURRENCE_STATUS_CHANGE, function(RecurrenceStatusChangeEvent $event) {
	 *     $oldStatus = $event->oldStatus;
	 *     // @var Order $order
	 *     $order = $event->sender;
	 * });
	 * ```
	 */
	const EVENT_RECURRENCE_STATUS_CHANGE = 'recurrenceStatusChange';

	/**
	 * @event craft\events\CancelableEvent This event is raised before an order is marked Imminent.
	 *
	 * ```php
	 * use craft\commerce\elements\Order;
	 * use steadfast\recurringorders\orders\RecurringOrderBehavior;
	 * use craft\events\CancelableEvent;
	 *
	 * Event::on(Order::class, RecurringOrderBehavior::EVENT_BEFORE_MARK_ORDER_IMMINENT, function(CancelableEvent $event) {
	 *     $event->isValid = false;
	 * });
	 * ```
	 */
	const EVENT_BEFORE_MARK_ORDER_IMMINENT = 'beforeMarkOrderImminent';

	/**
	 * @event yii\base\Event
	 *
	 * ```php
	 * use craft\commerce\elements\Order;
	 * use steadfast\recurringorders\orders\RecurringOrderBehavior;
	 * use yii\base\Event;
	 *
	 * Event::on(Order::class, RecurringOrderBehavior::EVENT_ORDER_MARKED_IMMINENT, function(Event $event) {
	 *     // @var Order $order
	 *     $order = $event->sender;
	 * });
	 * ```
	 */
	const EVENT_ORDER_MARKED_IMMINENT = 'orderMarkedImminent';


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
					TimeHelper::normalizeInterval($this->getRecurrenceInterval())
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
	 * @return int
	 */
	public function getRecurrenceErrorCount()
	{
		return (int)($this->_getRecord() ? $this->_getRecord()->errorCount : null);
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
	public function getRetryDate()
	{
		return $this->_getRecord() ? $this->_getRecord()->retryDate : null;
	}

	/**
	 * @param $value
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime
	 */
	public function setRetryDate($value)
	{
		// TODO: Typecasting and validation should probably go in the Record.
		$this->_getOrMakeRecord()->retryDate = (DateTimeHelper::toDateTime($value) ?: null);
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
	 * @return \DateTime|null
	 */
	public function getDateMarkedImminent()
	{
		return $this->_getRecord() ? $this->_getRecord()->dateMarkedImminent : null;
	}

	/**
	 * @return bool
	 */
	public function isMarkedImminent()
	{
		return ($record = $this->_getRecord()) && !empty($record->dateMarkedImminent);
	}

	/**
	 * @return int|null
	 */
	public function getRecurrencePaymentSourceId()
	{
		return $this->_getRecord() ? $this->_getRecord()->paymentSourceId : null;
	}

	/**
	 * @param $value
	 */
	public function setRecurrencePaymentSourceId($value)
	{
		$this->_getOrMakeRecord()->paymentSourceId = ((int)$value ?: null);
	}

	/**
	 * @return PaymentSource|null
	 */
	public function getRecurrencePaymentSource()
	{
		return $this->getRecurrencePaymentSourceId()
			? Commerce::getInstance()->paymentSources->getPaymentSourceById($this->getRecurrencePaymentSourceId())
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
	 * @return bool
	 */
	public function isDerived()
	{
		return (bool) $this->getOriginatingOrderId();
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
	public function isGenerated()
	{
		return (bool) $this->getParentOrderId();
	}

	/**
	 * @return OrderQuery
	 */
	public function findDerivedOrders()
	{
		/** @var RecurringOrderQuery $query */
		$query = Order::find();
		return $query->originatingOrderId($this->owner->id);
	}

	/**
	 * @return OrderQuery
	 */
	public function findGeneratedOrders()
	{
		/** @var RecurringOrderQuery $query */
		$query = Order::find();
		return $query->parentOrderId($this->owner->id);
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
		$newDate = TimeHelper::fromNow($this->getRecurrenceInterval());
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

		$recurrenceStatusChanged = $record->isAttributeChanged('status');
		$oldStatus = $record->getOldAttribute('status');

		$saved = $record->save();

		// Extra processing if the Recurrence Status changed
		if ($saved && $recurrenceStatusChanged)
		{

			// Raising the 'recurrenceStatusChange' event, from the owner Order
			if ($this->owner->hasEventHandlers(self::EVENT_RECURRENCE_STATUS_CHANGE)) {
				$event = new RecurrenceStatusChangeEvent([
					'oldStatus' => $oldStatus,
				]);
				$this->owner->trigger(self::EVENT_RECURRENCE_STATUS_CHANGE, $event);
			}

		}

		return $saved;

	}

	/**
	 * @return bool
	 *
	 * @throws \yii\db\Exception
	 */
	public function markImminent()
	{

		// Raising the 'beforeMarkOrderImminent' event, from the owner Order
		$event = new CancelableEvent();
		$this->owner->trigger(self::EVENT_BEFORE_MARK_ORDER_IMMINENT, $event);

		if (!$event->isValid)
		{
			return false;
		}

		$saved = $this->saveRecurringOrdersRecord([
			'dateMarkedImminent' => TimeHelper::now()
		]);

		if (!$saved)
		{
			return false;
		}

		// Raising the 'orderMarkedImminent' event, from the owner Order
		if ($this->owner->hasEventHandlers(self::EVENT_ORDER_MARKED_IMMINENT)) {
			$event = new Event();
			$this->owner->trigger(self::EVENT_ORDER_MARKED_IMMINENT, $event);
		}

		return true;

	}

	/**
	 * @return RecurringOrderHistoryRecord[]
	 */
	public function getRecurringOrderHistory()
	{
		return RecurringOrderHistoryRecord::findAll(['orderId' => $this->owner->id]);
	}

}
