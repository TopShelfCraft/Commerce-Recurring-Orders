<?php
namespace beSteadfast\RecurringOrders\orders;

use beSteadfast\RecurringOrders\meta\RecurringOrderQuery;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use beSteadfast\RecurringOrders\RecurringOrders;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\events\CancelableEvent;
use craft\helpers\DateTimeHelper;
use yii\base\Behavior;
use yii\base\Event;

/**
 * @property string|null $recurrenceStatus
 * @property string|null $recurrenceInterval
 * @property \DateTime|null $lastRecurrence
 * @property \DateTime|null $nextRecurrence
 * @property \DateTime|null $dateMarkedImminent
 * @property int|null $recurrencePaymentSourceId
 * @property string|null $note
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
	 * use beSteadfast\RecurringOrders\orders\RecurringOrderBehavior;
	 * use beSteadfast\RecurringOrders\orders\RecurrenceStatusChangeEvent;
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
	 * use beSteadfast\RecurringOrders\orders\RecurringOrderBehavior;
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
	 * use beSteadfast\RecurringOrders\orders\RecurringOrderBehavior;
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
	 * @var array
	 */
	private $_allDerivedOrders;

	/**
	 * @var array
	 */
	private $_allGeneratedOrders;

	/**
	 * @var array
	 */
	private $_recurringOrderHistory;

	/*
	 * Recurring Order Record
	 */

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
	 * @throws \yii\db\Exception if the RecurringOrderRecord cannot be saved.
	 *
	 * @todo If the record is completely empty, perhaps we should delete it from the db to keep things tidy?
	 * @todo Should probably throw exception rather than returning boolean (despite Yii returning a success boolean)
	 */
	public function saveRecurringOrdersRecord(?array $attributes): bool
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

	/*
	 * Getters, setters, markers
	 */

	public function getRecurringOrdersAttributes(array $names = null, array $except = []): array
	{
		return $this->_getRecord() ? $this->_getRecord()->getAttributes($names, $except) : [];
	}

	public function setRecurringOrdersAttributes(array $attributes = [], bool $safeOnly = false)
	{
		$this->_getOrMakeRecord()->setAttributes($attributes, $safeOnly);
	}

	/**
	 * Whether the Order is managed by the Recurring Orders plugin.
	 */
	public function hasRecurrenceStatus(): bool
	{
		return $this->_getRecord() ? !empty($this->_getRecord()->status) : false;
	}

	/**
	 * Whether the Order has both a Recurrence Interval and Next recurrence set.
	 */
	public function hasRecurrenceSchedule(): bool
	{
		return !empty($this->_getRecord()->recurrenceInterval) && !empty($this->_getRecord()->nextRecurrence);
	}

	public function getRecurrenceStatus(): ?string
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

	public function setRecurrenceStatus(string $value)
	{
		// TODO: Validate immediately?
		$this->_getOrMakeRecord()->status = $value;
	}

	public function getRecurrenceInterval(): ?string
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

	public function getHumanReadableRecurrenceInterval(): ?string
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

	public function getRecurrenceErrorReason(): ?string
	{
		return $this->_getRecord() ? $this->_getRecord()->errorReason : null;
	}

	public function setRecurrenceErrorReason(?string $value)
	{
		$this->_getOrMakeRecord()->errorReason = $value;
	}

	public function getRecurrenceErrorCount(): int
	{
		return (int)($this->_getRecord() ? $this->_getRecord()->errorCount : null);
	}

	public function setRecurrenceErrorCount(?int $value)
	{
		$this->_getOrMakeRecord()->errorCount = $value;
	}

	public function getRetryDate(): ?\DateTime
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

	public function getLastRecurrence(): ?\DateTime
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

	public function getNextRecurrence(): ?\DateTime
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

	public function getDateMarkedImminent(): ?\DateTime
	{
		return $this->_getRecord() ? $this->_getRecord()->dateMarkedImminent : null;
	}

	public function isMarkedImminent(): bool
	{
		return ($record = $this->_getRecord()) && !empty($record->dateMarkedImminent);
	}

	public function getRecurrencePaymentSourceId(): ?int
	{
		return $this->_getRecord() ? $this->_getRecord()->paymentSourceId : null;
	}

	public function setRecurrencePaymentSourceId(?int $value)
	{
		$this->_getOrMakeRecord()->paymentSourceId = $value;
	}

	public function getRecurrencePaymentSource(): ?PaymentSource
	{
		return $this->getRecurrencePaymentSourceId()
			? Commerce::getInstance()->paymentSources->getPaymentSourceById($this->getRecurrencePaymentSourceId())
			: null;
	}

	public function getRecurrenceNote(): ?string
	{
		return $this->_getRecord() ? $this->_getRecord()->note : null;
	}

	public function setRecurrenceNote(?string $value)
	{
		$this->_getOrMakeRecord()->note = (trim($value) ?: null);
	}

	public function getSpec(): Spec
	{
		if (!isset($this->_spec))
		{
			$config = $this->_getRecord() ? $this->_getRecord()->spec : null;
			$this->_spec = new Spec($config);
		}
		return $this->_spec;
	}

	public function getOriginatingOrderId(): ?int
	{
		return $this->_getRecord() ? $this->_getRecord()->originatingOrderId : null;
	}

	public function setOriginatingOrderId(?int $value)
	{
		$this->_getOrMakeRecord()->originatingOrderId = ((int)$value ?: null);
	}

	public function getOriginatingOrder(): ?Order
	{
		return $this->getOriginatingOrderId()
			? Commerce::getInstance()->orders->getOrderById($this->getOriginatingOrderId())
			: null;
	}

	public function isDerived(): bool
	{
		return (bool) $this->getOriginatingOrderId();
	}

	public function getParentOrderId(): ?int
	{
		return $this->_getRecord() ? $this->_getRecord()->parentOrderId : null;
	}

	public function setParentOrderId(?int $value)
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
	 * @return bool
	 */
	public function getResetNextRecurrenceOnSave()
	{
		return $this->_resetNextRecurrenceOnSave;
	}

	public function setResetNextRecurrenceOnSave(bool $value)
	{
		$this->_resetNextRecurrenceOnSave = $value;
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
	 * @todo Should this actually return a bool?
	 *
	 * @throws \yii\db\Exception
	 */
	public function markImminent(): bool
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

	/*
	 * Relations
	 */

	/**
	 * @return OrderQuery
	 */
	public function findDerivedOrders(): OrderQuery
	{
		/** @var RecurringOrderQuery $query */
		$query = Order::find();
		return $query->originatingOrderId($this->owner->id);
	}

	/**
	 * @return OrderQuery
	 */
	public function findGeneratedOrders(): OrderQuery
	{
		/** @var RecurringOrderQuery $query */
		$query = Order::find();
		return $query->parentOrderId($this->owner->id);
	}

	public function getAllDerivedOrders(): array
	{
		if (!isset($this->_allDerivedOrders))
		{
			$this->_allDerivedOrders = $this->findDerivedOrders()->all();
		}
		return $this->_allDerivedOrders;
	}

	public function getAllGeneratedOrdres(): array
	{
		if (!isset($this->_allGeneratedOrders))
		{
			$this->_allGeneratedOrders = $this->findGeneratedOrders()->all();
		}
		return $this->_allGeneratedOrders;
	}

	/**
	 * @return RecurringOrderHistoryRecord[]
	 */
	public function getRecurringOrderHistory(): array
	{
		if (!isset($this->_recurringOrderHistory))
		{
			$this->_recurringOrderHistory = RecurringOrderHistoryRecord::find()
				->where(['orderId' => $this->owner->id])
				->orderBy('id asc')
				->all();
		}
		return $this->_recurringOrderHistory;
	}

}
