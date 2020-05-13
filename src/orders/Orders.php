<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use topshelfcraft\recurringorders\misc\NormalizeTrait;
use topshelfcraft\recurringorders\misc\PaymentSourcesHelper;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\ErrorException;
use yii\base\Event;
use yii\base\Exception;
use yii\base\InvalidArgumentException;

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

		// Email (In case we're trying to add a customer email and create a Payment Source in the same request.)

		if ($email = $request->getParam('email'))
		{
			$order->setEmail($email);
		}

		// Order fields

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

		if ($paymentSourceAttributes = $request->getParam('makeRecurring.paymentSource'))
		{
			$paymentSource = $this->createPaymentSource($order, $paymentSourceAttributes);
			$order->setPaymentSourceId($paymentSource->id);
		}

		if (($paymentSourceId = $request->getParam('makeRecurring.paymentSourceId')) !== null)
		{
			$order->setPaymentSourceId($paymentSourceId ?: null);
		}

		// Spec fields

		if ($specStatus = $request->getParam('makeRecurring.spec.status'))
		{
			$order->setRecurrenceStatus($specStatus);
		}

		if ($specRecurrenceInterval = $request->getParam('makeRecurring.spec.recurrenceInterval'))
		{
			$order->getSpec()->setRecurrenceInterval($specRecurrenceInterval);
		}

		if ($specNextRecurrence = $request->getParam('makeRecurring.spec.nextRecurrence'))
		{
			$order->getSpec()->setNextRecurrence($specNextRecurrence);
		}

		if ($specPaymentSourceAttributes = $request->getParam('makeRecurring.spec.paymentSource'))
		{
			$specPaymentSource = $this->createPaymentSource($order, $specPaymentSourceAttributes);
			$order->getSpec()->setPaymentSourceId($specPaymentSource->id);
		}

		if (($specPaymentSourceId = $request->getParam('makeRecurring.spec.paymentSourceId')) !== null)
		{
			$order->getSpec()->setPaymentSourceId($specPaymentSourceId ?: null);
		}

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

		$this->replicateAsRecurring($order);

	}

	/**
	 * @param Order $order
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

	/**
	 * @param Order $order
	 *
	 * @return array
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	public function getPaymentSourceFormOptionsByOrder(Order $order)
	{
		return PaymentSourcesHelper::getPaymentSourceFormOptionsByOrder($order);
	}

	/**
	 * @param Order $order
	 * @param $attributes
	 *
	 * @return PaymentSource|null
	 *
	 * @throws Exception
	 */
	public function createPaymentSource(Order $order, $attributes)
	{

		/*
		 * Identify the Gateway
		 */

		$gatewayId = $attributes['gatewayId'] ?? $order->gatewayId;

		/** @var Gateway $gateway */
		$gateway = Commerce::getInstance()->gateways->getGatewayById($gatewayId);

		if (!$gateway || !$gateway->supportsPaymentSources()) {
			$error = Commerce::t('There is no gateway selected that supports payment sources.');
			throw new InvalidArgumentException($error);
		}

		/*
		 * Ensure a User
		 */

		try
		{
			$user = $this->_ensureOrderUser($order);
		}
		catch (\Throwable $e)
		{
			$error = Commerce::t('Could not create a User for Order.');
			throw new Exception($error);
		}

		/*
		 * Process the payment form
		 */

		// Get the payment method' gateway adapter's expected form model
		$paymentForm = $gateway->getPaymentFormModel();
		$paymentForm->setAttributes($attributes, false);
		$description = (string)($attributes['description'] ?? '');

		try
		{
			$paymentSource = Commerce::getInstance()->paymentSources->createPaymentSource($user->id, $gateway, $paymentForm, $description);
			return $paymentSource;
		}
		catch (\Throwable $e) {
			$error = Commerce::t('Could not create the payment source.');
			throw new Exception($error);
		}

	}

	/**
	 * @param Order $order
	 *
	 * @return User
	 *
	 * @throws Exception
	 * @throws \Throwable
	 * @throws \craft\errors\ElementNotFoundException
	 * @throws \yii\base\InvalidConfigException
	 */
	public function _ensureOrderUser(Order $order)
	{

		// Do we already have a User?
		if ($order->getUser())
		{
			return $order->getUser();
		}

		// Can't create a user without an email
		if (!$order->getEmail())
		{
			// TODO: Translate
			throw new Exception("Can't create a User without an email.");
		}

		// Need to associate the new User to the order's Customer
		$customer = $order->getCustomer();
		if (!$customer)
		{
			// TODO: Translate
			throw new Exception("The Order must have a Customer before saving a Payment Source.");
		}

		// Customer already a user? Commerce will link them up later.
		$user = User::find()->email($order->getEmail())->status(null)->one();
		if ($user)
		{
			return $user;
		}

		// Create a new user
		$user = new User();
		$user->email = $order->email;
		$user->unverifiedEmail = $order->email;
		$user->newPassword = null;
		$user->username = $order->email;
		$user->firstName = $order->billingAddress->firstName ?? '';
		$user->lastName = $order->billingAddress->lastName ?? '';
		$user->pending = true;
		$user->setScenario(Element::SCENARIO_ESSENTIALS); // Don't validate required custom fields.

		if (Craft::$app->elements->saveElement($user))
		{

			// Delete auto generated customer that was just created by Customers::afterSaveUserHandler()
			$autoGeneratedCustomer = Commerce::getInstance()->customers->getCustomerByUserId($user->id);
			if ($autoGeneratedCustomer) {
				Commerce::getInstance()->customers->deleteCustomer($autoGeneratedCustomer);
			}

			// Re-associate the new user to the Order's customer.
			$customer->userId = $user->id;
			Commerce::getInstance()->customers->saveCustomer($customer, false);

		}
		else
		{
			// TODO: Translate.
			$errors = $user->getErrors();
			RecurringOrders::error($errors);
			throw new Exception("Could not create a User to own this order.");
		}

		return $user;

	}

}
