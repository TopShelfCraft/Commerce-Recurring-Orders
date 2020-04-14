<?php
namespace topshelfcraft\recurringorders\orders;

use craft\commerce\elements\db\OrderQuery;
use craft\events\CancelableEvent;
use craft\helpers\Db;
use yii\base\Behavior;

/**
 * @property OrderQuery $owner
 */
class RecurringOrderQueryBehavior extends Behavior
{

	/**
	 * @var bool
	 */
	public $hasRecurrenceStatus;

	/**
	 * @var bool
	 */
	public $hasRecurrenceSchedule;

	/**
	 * @var mixed
	 */
	public $recurrenceStatus;

	/**
	 * @var mixed
	 */
	public $recurrenceErrorReason;

	/**
	 * @var mixed
	 */
	public $recurrenceErrorCount;

	/**
	 * @var mixed
	 */
	public $recurrenceInterval;

	/**
	 * @var mixed
	 */
	public $lastRecurrence;

	/**
	 * @var mixed
	 */
	public $nextRecurrence;

	/**
	 * @var bool
	 */
	public $hasOriginatingOrder;

	/**
	 * @var mixed
	 */
	public $originatingOrderId;

	/**
	 * @var bool
	 */
	public $hasParentOrder;

	/**
	 * @var mixed
	 */
	public $parentOrderId;

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			OrderQuery::EVENT_AFTER_PREPARE => 'afterPrepare',
		];
	}

	/**
	 * @param bool $value
	 *
	 * @return OrderQuery
	 */
	public function hasRecurrenceStatus($value = true)
	{
		$this->hasRecurrenceStatus = is_null($value) ? $value : (bool) $value;
		return $this->owner;
	}

	/**
	 * @param bool $value
	 *
	 * @return OrderQuery
	 */
	public function hasRecurrenceSchedule($value = true)
	{
		$this->hasRecurrenceSchedule = is_null($value) ? $value : (bool) $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function recurrenceStatus($value)
	{
		$this->recurrenceStatus = $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function recurrenceErrorReason($value)
	{
		$this->recurrenceErrorReason = $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function recurrenceErrorCount($value)
	{
		$this->recurrenceErrorCount = $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function recurrenceInterval($value)
	{
		$this->recurrenceInterval = $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function lastRecurrence($value)
	{
		$this->lastRecurrence = $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function nextRecurrence($value)
	{
		$this->nextRecurrence = $value;
		return $this->owner;
	}

	/**
	 * @param bool $value
	 *
	 * @return OrderQuery
	 */
	public function hasOriginatingOrder($value = true)
	{
		$this->hasOriginatingOrder = is_null($value) ? $value : (bool) $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function originatingOrderId($value)
	{
		$this->originatingOrderId = $value;
		return $this->owner;
	}

	/**
	 * @param bool $value
	 *
	 * @return OrderQuery
	 */
	public function hasParentOrder($value = true)
	{
		$this->hasParentOrder = is_null($value) ? $value : (bool) $value;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function parentOrderId($value)
	{
		$this->parentOrderId = $value;
		return $this->owner;
	}

	/**
	 * @param CancelableEvent $event
	 */
	public function afterPrepare(CancelableEvent $event)
	{

		/** @var OrderQuery $orderQuery */
		$orderQuery = $event->sender;

		$joinTable = RecurringOrderRecord::tableName() . " recurringOrders";
		$orderQuery->query->leftJoin($joinTable, "[[recurringOrders.id]] = [[subquery.elementsId]]");
		$orderQuery->subQuery->leftJoin($joinTable, "[[recurringOrders.id]] = [[elements.id]]");

		if ($this->hasRecurrenceStatus !== null) {
			$orderQuery->subQuery->andWhere([($this->hasRecurrenceStatus ? 'is not' : 'is'), '[[recurringOrders.status]]', null]);
		}

		if ($this->hasRecurrenceSchedule === true) {
			$orderQuery->subQuery->andWhere([
				'and',
				['is not', '[[recurringOrders.recurrenceInterval]]', null],
				['is not', '[[recurringOrders.nextRecurrence]]', null],
			]);
		}

		if ($this->hasRecurrenceSchedule === false) {
			$orderQuery->subQuery->andWhere([
				'or',
				['is', '[[recurringOrders.recurrenceInterval]]', null],
				['is', '[[recurringOrders.nextRecurrence]]', null],
			]);
		}

		if ($this->recurrenceStatus)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.status', $this->recurrenceStatus));
		}

		if ($this->recurrenceErrorReason)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.errorReason', $this->recurrenceErrorReason));
		}

		if ($this->recurrenceErrorCount !== null)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.errorCount', $this->recurrenceErrorCount));
		}

		if ($this->recurrenceInterval !== null)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.recurrenceInterval', $this->recurrenceInterval));
		}

		if ($this->lastRecurrence)
		{
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.lastRecurrence', $this->lastRecurrence));
		}

		if ($this->nextRecurrence)
		{
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.nextRecurrence', $this->nextRecurrence));
		}

		if ($this->hasOriginatingOrder !== null) {
			$orderQuery->subQuery->andWhere([($this->hasOriginatingOrder ? 'is not' : 'is'), '[[recurringOrders.originatingOrderId]]', null]);
		}

		if ($this->originatingOrderId)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.originatingOrderId', $this->originatingOrderId));
		}

		if ($this->hasParentOrder !== null) {
			$orderQuery->subQuery->andWhere([($this->hasParentOrder ? 'is not' : 'is'), '[[recurringOrders.parentOrderId]]', null]);
		}

		if ($this->parentOrderId)
		{
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.parentOrderId', $this->parentOrderId));
		}

	}

}
