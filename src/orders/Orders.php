<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use topshelfcraft\recurringorders\misc\NormalizeTrait;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\Exception;

class Orders extends Component
{

	use NormalizeTrait;

	/**
	 * @event \yii\base\Event This event is raised when new Recurring Orders are potentially being derived from a newly completed Order.
	 *
	 * ```php
	 * use craft\commerce\elements\Order;
	 * use topshelfcraft\recurringorders\orders\DerivedOrdersEvent;
	 *
	 * Event::on(Orders::class, Order::EVENT_AFTER_PREPARE_DERIVED_ORDERS, function(DerivedOrdersEvent $event) {
	 *     // @var Order $order
	 *     $originatingOrder = $event->originatingOrder;
	 *     // @var Order[] $derivedOrders
	 *     $derivedOrders = $event->derivedOrders;
	 * });
	 * ```
	 */
	const EVENT_AFTER_PREPARE_DERIVED_ORDERS = 'afterPrepareDerivedOrders';

	/**
	 * @event \yii\base\Event This event is raised after newly derived Recurring Orders have been saved and completed.
	 *
	 * ```php
	 * use craft\commerce\elements\Order;
	 * use topshelfcraft\recurringorders\orders\DerivedOrdersEvent;
	 *
	 * Event::on(Orders::class, Order::EVENT_AFTER_COMPLETE_DERIVED_ORDERS, function(DerivedOrdersEvent $event) {
	 *     // @var Order $order
	 *     $originatingOrder = $event->originatingOrder;
	 *     // @var Order[] $derivedOrders
	 *     $derivedOrders = $event->derivedOrders;
	 * });
	 * ```
	 */
	const EVENT_AFTER_COMPLETE_DERIVED_ORDERS = 'afterCompleteDerivedOrders';

	/**
	 * Return a list of all possible Recurrence Status labels, keyed by the status value.
	 *
	 * @return array
	 */
	public function getAllRecurrenceStatuses()
	{
		return [
			RecurringOrderRecord::STATUS_ACTIVE => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_ACTIVE),
			RecurringOrderRecord::STATUS_UNSCHEDULED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_UNSCHEDULED),
			RecurringOrderRecord::STATUS_PAUSED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_PAUSED),
			RecurringOrderRecord::STATUS_CANCELLED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_CANCELLED),
			RecurringOrderRecord::STATUS_ERROR => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_ERROR),
		];
	}

	/**
	 * @param Order $order The Commerce Order
	 * @param array $attributes Additional attributes to set on the Recurring Order record
	 * @param bool $resetNextRecurrence Whether the Next Occurrence should be reset from the present time (if possible).
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 *
	 * @deprecated
	 */
	public function makeOrderRecurring(Order $order, $attributes = [], $resetNextRecurrence = false)
	{

		/** @var RecurringOrderBehavior $order */

		// Default status, if none provided
		if (!isset($attributes['status']))
		{
			$attributes['status'] = RecurringOrderRecord::STATUS_ACTIVE;
		}

		$order->setRecurringOrdersAttributes($attributes);

		if ($resetNextRecurrence)
		{
			$order->resetNextRecurrence();
		}

		return $order->saveRecurringOrdersRecord();

		// TODO: Deprecate this.

	}



	/**
	 * Intercepts Order Save events *before* saving begins, and, if it appears we're in the middle of an Action request,
	 * processes any given `makeRecurring` attributes.
	 *
	 * @param Order $order
	 *
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function beforeSaveOrder(Order $order)
	{

		$request = Craft::$app->request;

		if (!$request->getIsActionRequest() || !$request->getParam('makeRecurring'))
		{
			return;
		}

		/** @var RecurringOrderBehavior $order */

		if ($status = $request->getParam('makeRecurring.status'))
		{
			$order->setRecurrenceStatus($status);
		}

		if ($recurrenceInterval = $request->getParam('makeRecurring.recurrenceInterval'))
		{
			$order->setRecurrenceInterval($recurrenceInterval);
		}

		if ($nextRecurrence = $request->getParam('makeRecurring.nextRecurrence'))
		{
			$order->setNextRecurrence($nextRecurrence);
		}

		if (($resetNextRecurrence = $request->getParam('makeRecurring.resetNextRecurrence')) !== null)
		{
			$order->setResetNextRecurrenceOnSave(self::normalizeBoolean($resetNextRecurrence));
		}

		// TODO: Process `spec` fields

	}

	/**
	 * Intercepts Order Save events *after* the Order is saved, and saves the associated RecurringOrderRecord.
	 *
	 * @param Order $order
	 *
	 * @return void
	 *
	 * @throws \Exception to interrupt the Order Save event if the RecurringOrderRecord cannot be saved.
	 */
	public function afterSaveOrder(Order $order)
	{

		/** @var RecurringOrderBehavior $order */

		if ($order->getResetNextRecurrenceOnSave())
		{
			$order->resetNextRecurrence();
		}

		$success = $order->saveRecurringOrdersRecord();

		if (!$success)
		{
			throw new ErrorException("Could not save the Recurring Order record.");
		}

	}

	/**
	 * @param Event $event
	 */
	public function afterCompleteOrder(Event $event)
	{

		$order = $event->sender;
		/** @var Order $order */

		/*
		 * To prevent an infinite spiral of derived or generated orders, bail early if this newly completed order
		 * already has an Originating Order marked on it (i.e. it is already "derived")
		 * or if it has a Parent Order (i.e. it is already "generated").
		 */
		if ($order->getOriginatingOrderId() || $order->getParentOrderId())
		{
			return;
		}

		$derivedOrders = $this->_prepareDerivedOrders($order);

		foreach ($derivedOrders as $derivedOrder)
		{
			Craft::$app->getElements()->saveElement($derivedOrder);
			$derivedOrder->markAsComplete();
		}

		// Raising the 'afterCompleteDerivedOrders' event
		$event = new DerivedOrdersEvent([
			'originatingOrder' => $order,
			'derivedOrders' => $derivedOrders,
		]);
		if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_DERIVED_ORDERS)) {
			$this->trigger(self::EVENT_AFTER_COMPLETE_DERIVED_ORDERS, $event);
		}

	}

	/**
	 * @param Order $originatingOrder
	 *
	 * @return Order[]
	 */
	private function _prepareDerivedOrders(Order $originatingOrder)
	{

		$derivedOrders = [];

		/** @var RecurringOrderBehavior $originatingOrder **/
		$spec = $originatingOrder->getSpec();

		if (!$spec->isEmpty())
		{

			$newOrder = $this->getLightClone($originatingOrder);

			/** @var RecurringOrderBehavior $newOrder */

			$newOrder->setOriginatingOrderId($originatingOrder->id);

			$newOrder->setRecurrenceStatus($spec->getStatus() ?: RecurringOrderRecord::STATUS_ACTIVE);
			$newOrder->setRecurrenceInterval($spec->getRecurrenceInterval());
			$newOrder->setPaymentSourceId($spec->getPaymentSourceId());

			try
			{
				$newOrder->setNextRecurrence($spec->getNextRecurrence());
				if (!$newOrder->getNextRecurrence())
				{
					$newOrder->resetNextRecurrence();
				}
			}
			catch (\Exception $e)
			{
				RecurringOrders::error($e->getMessage());
			}

			$derivedOrders = [$newOrder];

		}

		// Raising the 'afterPrepareDerivedOrders' event
		$event = new DerivedOrdersEvent([
			'originatingOrder' => $originatingOrder,
			'derivedOrders' => $derivedOrders,
		]);
		if ($this->hasEventHandlers(self::EVENT_AFTER_PREPARE_DERIVED_ORDERS)) {
			$this->trigger(self::EVENT_AFTER_PREPARE_DERIVED_ORDERS, $event);
		}

		return $event->derivedOrders;

	}

	/**
	 * @param Order $order
	 *
	 * @return Order
	 */
	public function getLightClone(Order $order)
	{

		$attributesToClone = [
			'billingAddressId',
			'shippingAddressId',
			'estimatedBillingAddressId',
			'estimatedShippingAddressId',
			'gatewayId',
			'paymentSourceId',
			'customerId',
			'couponCode',
			'email',
			'currency',
			'paymentCurrency',
			'orderLanguage',
			'origin',
			'shippingMethodHandle'
		];

		$attributes = $order->getAttributes($attributesToClone);

		$clone = new Order();
		$clone->number = Commerce::getInstance()->getCarts()->generateCartNumber();
		$clone->setAttributes($attributes, false);
		Craft::$app->elements->saveElement($clone);

		$lineItems = [];
		foreach ($order->getLineItems() as $lineItem)
		{
			$lineItems[] = Commerce::getInstance()->lineItems->createLineItem($order->id, $lineItem->purchasableId, $lineItem->getOptions(), $lineItem->qty, $lineItem->note);
		}
		$clone->setLineItems($lineItems);

		return $clone;

	}

	/**
	 * @param Order $parentOrder
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function processOrderRecurrence(Order $parentOrder)
	{

		/** @var RecurringOrderBehavior $parentOrder */

		// TODO: Only pass if the Parent Order has a Recurrence Schedule?
		if (!$parentOrder->hasRecurrenceStatus())
		{
			// TODO: Translate?
			throw new Exception("Cannot process recurrence on a non-recurring Order.");
		}

		$newOrder = $this->getLightClone($parentOrder);
		/** @var RecurringOrderBehavior $newOrder */
		$newOrder->parentOrderId = $parentOrder->id;

		$success = true;
		$errorReason = null;

		/*
		 * Check Payment Source
		 */
		$paymentSource = $newOrder->getPaymentSource();

		if (!$paymentSource)
		{
//			$success = false;
//			// TODO: Translate?
//			RecurringOrders::error("Cannot process Recurring Order because Payment Source is missing.");
//			$errorReason = RecurringOrderRecord::ERROR_NO_PAYMENT_SOURCE;
//			// TODO: Trigger error and return early
		}

		// TODO: Check for line item availability errors (ERROR_PRODUCT_UNAVAILABLE)

		// TODO: Check for discount errors (ERROR_DISCOUNT_UNAVAILABLE)

		// TODO: Check for Payment Source expiration (ERROR_PAYMENT_SOURCE_EXPIRED)

		// TODO: (Start transaction...)

		/*
		 * Save the new Order...
		 */

		try
		{
			$success = $success && Craft::$app->getElements()->saveElement($newOrder);
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
		}

		/*
		 * Complete the Order...
		 */

		try
		{
			$newOrder->recalculate();
			$success = $success && $newOrder->markAsComplete();
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
		}

		/*
		 * Process the payment...
		 */

		try
		{
//			$paymentForm = $newOrder->getGateway()->getPaymentFormModel();
//			$paymentForm->populateFromPaymentSource($paymentSource);
//			// Only bother trying the payment if we've been successful so far.
//			$success && Commerce::getInstance()->getPayments()->processPayment($newOrder, $paymentForm, $redirect, $transaction);
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
			$errorReason = RecurringOrderRecord::ERROR_PAYMENT_ISSUE;
		}

		// TODO: (Commit/rollback transaction.)

		/*
		 * Process success!
		 */

		if ($success)
		{

			$this->makeOrderRecurring($parentOrder, [
				'status' => RecurringOrderRecord::STATUS_ACTIVE,
				'errorReason' => null,
				'errorCount' => null,
			], true);

			// TODO: Trigger success events.

			return true;

		}

		/*
		 * Process remaining errors.  :-/
		 */

		$this->makeOrderRecurring($parentOrder, [
			'status' => RecurringOrderRecord::STATUS_ERROR,
			'errorReason' => $errorReason,
			'errorCount' => intval($parentOrder->getRecurrenceErrorCount()) + 1,
		], false);

		// TODO: Trigger error events.

		return false;

	}

}
