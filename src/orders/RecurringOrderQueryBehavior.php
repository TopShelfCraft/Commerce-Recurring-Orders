<?php
namespace beSteadfast\RecurringOrders\orders;

use craft\commerce\elements\db\OrderQuery;
use craft\events\CancelableEvent;
use craft\helpers\Db;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use beSteadfast\RecurringOrders\RecurringOrders;
use yii\base\Behavior;
use yii\base\Exception;

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
	public $recurrenceRetryDate;

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
	 * @var mixed
	 */
	public $dateMarkedImminent;

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
	 * @var bool|null
	 */
	public $isOutstanding;

	/**
	 * @var true|null
	 */
	public $isEligibleForRecurrence;

	/**
	 * @var bool|null
	 */
	public $isMarkedImminent;

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
	public function recurrenceRetryDate($value)
	{
		$this->recurrenceRetryDate = $value;
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
	 * @param bool|null $value
	 *
	 * @return OrderQuery
	 */
	public function isOutstanding($value = true)
	{
		$this->isOutstanding = is_null($value) ? null : (bool) $value;
		return $this->owner;
	}

	/**
	 * @param true|null $value
	 *
	 * @return OrderQuery
	 */
	public function isEligibleForRecurrence($value = true)
	{
		if ($value === false)
		{
			throw new Exception("Negative query not implemented for this scope.");
		}
		$this->isEligibleForRecurrence = is_null($value) ? null : true;
		return $this->owner;
	}

	/**
	 * @param mixed $value
	 *
	 * @return OrderQuery
	 */
	public function dateMarkedImminent($value)
	{
		$this->dateMarkedImminent = $value;
		return $this->owner;
	}

	/**
	 * @param bool|null $value
	 *
	 * @return OrderQuery
	 */
	public function isMarkedImminent($value = true)
	{
		$this->isMarkedImminent = is_null($value) ? null : (bool) $value;
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

		if ($this->recurrenceRetryDate)
		{
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.retryDate', $this->recurrenceRetryDate));
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

		if ($this->isOutstanding)
		{
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.nextRecurrence', '<'.TimeHelper::now()->getTimestamp()));
		}

		if ($this->isEligibleForRecurrence)
		{

			// Only select orders with eligible status.
			$eligibleStatuses = [
				RecurringOrderRecord::STATUS_ACTIVE,
				RecurringOrderRecord::STATUS_ERROR,
			];
			$orderQuery->subQuery->andWhere(Db::parseParam('recurringOrders.status', $eligibleStatuses));

			// Only select orders where the Next Recurrence is past.
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.nextRecurrence', '<'.TimeHelper::now()->getTimestamp()));

			// Only select orders where the Retry Date is null or past.
			$orderQuery->subQuery->andWhere([
				'or',
				['is', '[[recurringOrders.retryDate]]', null],
				Db::parseDateParam('recurringOrders.retryDate', '<'.TimeHelper::now()->getTimestamp()),
			]);

			// If we have configured a max error count, only select orders where the Error Count is null or less than the limit.
			if ($errorLimit = (int) RecurringOrders::getInstance()->getSettings()->maxRecurrenceErrorCount)
			{
				$orderQuery->subQuery->andWhere([
					'or',
					['is', '[[recurringOrders.errorCount]]', null],
					Db::parseParam('recurringOrders.errorCount', $errorLimit, '<')
				]);
			}

		}

		if ($this->dateMarkedImminent)
		{
			$orderQuery->subQuery->andWhere(Db::parseDateParam('recurringOrders.dateMarkedImminent', $this->dateMarkedImminent));
		}

		if ($this->isMarkedImminent)
		{
			$orderQuery->subQuery->andWhere([($this->dateMarkedImminent ? 'is not' : 'is'), '[[recurringOrders.dateMarkedImminent]]', null]);
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
