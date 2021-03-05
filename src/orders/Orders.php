<?php
namespace beSteadfast\RecurringOrders\orders;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\events\ModelEvent;
use beSteadfast\RecurringOrders\meta\RecurringOrder;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use beSteadfast\RecurringOrders\misc\NormalizeTrait;
use beSteadfast\RecurringOrders\misc\PaymentSourcesHelper;
use beSteadfast\RecurringOrders\RecurringOrders;
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
	 * use beSteadfast\RecurringOrders\orders\DerivedOrdersEvent;
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
	 * use beSteadfast\RecurringOrders\orders\DerivedOrdersEvent;
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
	 */
	public function getAllRecurrenceStatuses(): array
	{
		return [
			RecurringOrderRecord::STATUS_ACTIVE => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_ACTIVE),
			RecurringOrderRecord::STATUS_UNSCHEDULED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_UNSCHEDULED),
			RecurringOrderRecord::STATUS_PAUSED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_PAUSED),
			RecurringOrderRecord::STATUS_CANCELLED => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_CANCELLED),
			RecurringOrderRecord::STATUS_ERROR => RecurringOrders::t('_status:'.RecurringOrderRecord::STATUS_ERROR),
		];
	}

	public function getPaymentSourceFormOptionsByOrder(Order $order): array
	{
		return PaymentSourcesHelper::getPaymentSourceFormOptionsByOrder($order);
	}

	public function getLightClone(Order $order): Order
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
			$lineItems[] = Commerce::getInstance()->lineItems->createLineItem($clone->id, $lineItem->purchasableId, $lineItem->getOptions(), $lineItem->qty, $lineItem->note, $clone);
		}
		$clone->setLineItems($lineItems);

		Craft::$app->elements->saveElement($clone);

		return $clone;

	}

	/**
	 * Intercepts Order Save events *before* saving begins, and, if it appears we're in the middle of an Action request,
	 * processes any given `recurringOrder` attributes.
	 *
	 * @throws \Exception
	 */
	public function handleOrderBeforeSave(ModelEvent $event): void
	{

		/** @var RecurringOrder $order */
		$order = $event->sender;

		$request = Craft::$app->request;

		if (!$request->getIsActionRequest() || !$request->getParam('recurringOrder'))
		{
			return;
		}

		// Order fields

		if ($status = $request->getParam('recurringOrder.status'))
		{
			$order->setRecurrenceStatus($status);
		}

		if ($recurrenceInterval = $request->getParam('recurringOrder.recurrenceInterval'))
		{
			$order->setRecurrenceInterval($recurrenceInterval);
		}

		if ($nextRecurrence = $request->getParam('recurringOrder.nextRecurrence'))
		{
			$order->setNextRecurrence($nextRecurrence);
		}

		if (($resetNextRecurrence = $request->getParam('recurringOrder.resetNextRecurrence')) !== null)
		{
			$order->setResetNextRecurrenceOnSave(self::normalizeBoolean($resetNextRecurrence));
		}

		if (($paymentSourceId = $request->getParam('recurringOrder.paymentSourceId')) !== null)
		{
			$order->setRecurrencePaymentSourceId($paymentSourceId ?: null);
		}

		// Spec fields

		if ($specStatus = $request->getParam('recurringOrder.spec.status'))
		{
			$order->getSpec()->setStatus($specStatus);
		}

		if ($specRecurrenceInterval = $request->getParam('recurringOrder.spec.recurrenceInterval'))
		{
			$order->getSpec()->setRecurrenceInterval($specRecurrenceInterval);
		}

		if ($specNextRecurrence = $request->getParam('recurringOrder.spec.nextRecurrence'))
		{
			$order->getSpec()->setNextRecurrence($specNextRecurrence);
		}

		if (($specPaymentSourceId = $request->getParam('recurringOrder.spec.paymentSourceId')) !== null)
		{
			$order->getSpec()->setRecurrencePaymentSourceId($specPaymentSourceId ?: null);
		}

	}

	/**
	 * Intercepts Order Save events *after* the Order is saved, and saves the associated RecurringOrderRecord.
	 *
	 * @throws \Exception to interrupt the Order Save event if the RecurringOrderRecord cannot be saved.
	 */
	public function handleOrderAfterSave(ModelEvent $event): void
	{

		/** @var RecurringOrder $order */
		$order = $event->sender;

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

	public function handleAfterCompleteOrder(Event $event): void
	{

		$order = $event->sender;
		/** @var Order $order */

		/*
		 * To prevent an infinite spiral of derived or generated orders, bail early if this newly completed order
		 * already has an Originating Order (i.e. it is "derived"), or if it has a Parent Order (i.e. it is "generated").
		 */
		if ($order->isGenerated() || $order->isDerived())
		{
			return;
		}

		$this->replicateAsRecurring($order);

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

		/** @var RecurringOrder $order */

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

	}

	/**
	 * @param Order $parentOrder
	 *
	 * @return true Because I was young and foolish.
	 * @todo Remove return value.
	 *
	 * @throws \Exception from `processOrderRecurrenceError()`
	 */
	public function processOrderRecurrence(Order $parentOrder)
	{

		// TODO: Use a specific Exception instead of generic Yii Exception.

		/** @var RecurringOrder $parentOrder */

		if (!$parentOrder->hasRecurrenceStatus())
		{
			throw new Exception("Cannot process recurrence on a non-recurring Order.");
		}

		/*
		 * Check Payment Source
		 */
		$paymentSource = $parentOrder->getRecurrencePaymentSource();

		if (!$paymentSource)
		{
			$this->processOrderRecurrenceError($parentOrder, RecurringOrderRecord::ERROR_NO_PAYMENT_SOURCE);
			throw new Exception("Cannot process Recurring Order because Payment Source is missing.");
		}

		/*
		 * Create the new Order.
		 */

		/** @var RecurringOrder $newOrder */
		$newOrder = $this->getLightClone($parentOrder);
		$newOrder->setParentOrderId($parentOrder->id);

		/*
		 * TODO: Check for line item availability errors (ERROR_PRODUCT_UNAVAILABLE)
		 */

		/*
		 * TODO: Check for discount errors (ERROR_DISCOUNT_UNAVAILABLE)
		 */

		/*
		 * TODO: (Start transaction...)
		 */

		/*
		 * Save the new Order...
		 */

		try
		{
			$success = Craft::$app->getElements()->saveElement($newOrder);
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
		}

		if (!$success)
		{
			$this->processOrderRecurrenceError($parentOrder, RecurringOrderRecord::ERROR_UNKNOWN);
			throw new Exception("Could not save the Generated Order.");
		}

		/*
		 * Process the payment...
		 */

		try
		{
			/*
			 * Gotta set the Payment Source on the new order first, because it will determine which Gateway gets used
			 * for the payment.
			 *
			 * (For some reason, using `$paymentSource->getGateway()->getPaymentFormModel()` directly,
			 * without invoking `$newOrder->setPaymentSource()` as an intermediate step,
			 * produced a "Non-numeric value" error, which I haven't tracked down yet.) ^MR 2020-07-29
			 */
			$newOrder->setPaymentSource($paymentSource);
			$paymentForm = $newOrder->getGateway()->getPaymentFormModel();
			$paymentForm->populateFromPaymentSource($paymentSource);
			Commerce::getInstance()->getPayments()->processPayment($newOrder, $paymentForm, $redirect, $transaction);
		}
		catch(\Throwable $e)
		{
			$this->processOrderRecurrenceError($parentOrder, RecurringOrderRecord::ERROR_PAYMENT_ISSUE);
			throw new Exception("Could not process payment for the Generated Order.");
		}

		// TODO: (Commit/rollback transaction.)

		/*
		 * Process success!
		 */

		$this->makeOrderRecurring($parentOrder, [
			// TODO: Should we be resetting to Active every time? Or only if it was previously in error status?
			'status' => RecurringOrderRecord::STATUS_ACTIVE,
			'errorReason' => null,
			'errorCount' => null,
			'retryDate' => null,
			'lastRecurrence' => TimeHelper::now(),
			'dateMarkedImminent' => null,
		], true);

		// TODO: Trigger success events.

		return true;

	}

	/**
	 * @throws \Exception
	 */
	public function processOrderRecurrenceError(Order $parentOrder, string $errorReason)
	{

		/** @var RecurringOrder $parentOrder */

		$settings = RecurringOrders::getInstance()->getSettings();
		$retryInterval = TimeHelper::normalizeInterval($settings->retryInterval);

		$this->makeOrderRecurring($parentOrder, [
			'status' => RecurringOrderRecord::STATUS_ERROR,
			'errorReason' => $errorReason,
			'errorCount' => $parentOrder->getRecurrenceErrorCount() + 1,
			'retryDate' => TimeHelper::fromNow($retryInterval),
		], false);

		// TODO: Trigger error events.

	}

	/**
	 * @return bool Because I was young and foolish.
	 * @todo Remove boolean return
	 *
	 * @throws Exception from `saveElement()`
	 * @throws \Throwable from `markAsComplete()`
	 * @throws \craft\commerce\errors\OrderStatusException from `markAsComplete()`
	 * @throws \craft\errors\ElementNotFoundException from `markAsComplete()`
	 */
	public function replicateAsRecurring(Order $order)
	{

		$success = true;

		$derivedOrders = $this->_prepareDerivedOrders($order);

		foreach ($derivedOrders as $derivedOrder)
		{
			$success = $success && Craft::$app->getElements()->saveElement($derivedOrder);
			$success = $success && $derivedOrder->markAsComplete();
		}

		// Raising the 'afterCompleteDerivedOrders' event
		$event = new DerivedOrdersEvent([
			'originatingOrder' => $order,
			'derivedOrders' => $derivedOrders,
		]);
		if ($this->hasEventHandlers(self::EVENT_AFTER_COMPLETE_DERIVED_ORDERS)) {
			$this->trigger(self::EVENT_AFTER_COMPLETE_DERIVED_ORDERS, $event);
		}

		return $success;

	}

	/**
	 * @return Order[]
	 */
	private function _prepareDerivedOrders(Order $originatingOrder): array
	{

		/** @var RecurringOrder $originatingOrder **/

		$derivedOrders = [];

		$spec = $originatingOrder->getSpec();

		if (!$spec->isEmpty())
		{

			/** @var RecurringOrder $newOrder */
			$newOrder = $this->getLightClone($originatingOrder);

			$newOrder->setOriginatingOrderId($originatingOrder->id);

			$newOrder->setRecurrenceStatus($spec->getStatus() ?: RecurringOrderRecord::STATUS_ACTIVE);
			$newOrder->setRecurrenceInterval($spec->getRecurrenceInterval());
			$newOrder->setRecurrencePaymentSourceId($spec->getRecurrencePaymentSourceId());

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

}
