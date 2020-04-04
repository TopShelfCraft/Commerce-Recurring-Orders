<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\events\RegisterElementDefaultTableAttributesEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\web\View;
use topshelfcraft\recurringorders\misc\NormalizeTrait;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\ErrorException;
use yii\base\Exception;

class Orders extends Component
{

	use NormalizeTrait;

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

		$lineItems = $order->getLineItems();
		foreach ($lineItems as $lineItem)
		{
			// Clear out the LineItem ID so that we save, we get a new LineItem record, rather than overwriting the original one.
			$lineItem->id = null;
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

		if (!$parentOrder->getIsRecurring())
		{
			// TODO: Translate?
			throw new Exception("Cannot process recurrence on a non-recurring Order.");
		}

		$newOrder = $this->getLightClone($parentOrder);

		$success = true;
		$errorReason = null;

		/*
		 * Check Payment Source
		 */
		$paymentSource = $newOrder->getPaymentSource();

		if (!$paymentSource)
		{
			$success = false;
			// TODO: Translate?
			RecurringOrders::error("Cannot process Recurring Order because Payment Source is missing.");
			$errorReason = RecurringOrderRecord::ERROR_NO_PAYMENT_SOURCE;
			// TODO: Trigger error and return early
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
			$paymentForm = $newOrder->getGateway()->getPaymentFormModel();
			$paymentForm->populateFromPaymentSource($paymentSource);
			// Only bother trying the payment if we've been successful so far.
			$success && Commerce::getInstance()->getPayments()->processPayment($newOrder, $paymentForm, $redirect, $transaction);
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
			$errorReason = RecurringOrderRecord::ERROR_PAYMENT_ISSUE;
		}

		/*
		 * Record a new Generated Order
		 */

		try
		{
			$generatedOrderRecord = new GeneratedOrderRecord([
				'orderId' => $newOrder->id,
				'parentOrderId' => $parentOrder->id,
			]);
			$success = $success && $generatedOrderRecord->save();
		}
		catch(\Throwable $e)
		{
			$success = false;
			RecurringOrders::error($e->getMessage());
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
			'errorCount' => intval($parentOrder->getRecurringOrderErrorCount()) + 1,
		], false);

		// TODO: Trigger error events.

		return false;

	}

	/**
	 * @param RegisterElementSortOptionsEvent $event
	 */
	public static function registerSortOptions(RegisterElementSortOptionsEvent $event)
	{
		$event->sortOptions = $event->sortOptions + [
			'recurringOrders.status' => RecurringOrders::t('Recurrence Status'),
			'recurringOrders.lastRecurrence' => RecurringOrders::t('Last Recurrence'),
			'recurringOrders.nextRecurrence' => RecurringOrders::t('Next Recurrence'),
			'recurringOrders.errorCount' => RecurringOrders::t('Recurrence Error Count'),
			];
	}

	/**
	 * @param RegisterElementTableAttributesEvent $event
	 */
	public static function registerTableAttributes(RegisterElementTableAttributesEvent $event)
	{
		$event->tableAttributes = $event->tableAttributes + [
			'recurrenceStatus' => RecurringOrders::t('Recurrence Status'),
			'lastRecurrence' => RecurringOrders::t('Last Recurrence'),
			'nextRecurrence' => RecurringOrders::t('Next Recurrence'),
			'recurrenceErrorCount' => RecurringOrders::t('Recurrence Error Count'),
			];
	}

	/**
	 * @param RegisterElementDefaultTableAttributesEvent $event
	 */
	public static function registerDefaultTableAttributes(RegisterElementDefaultTableAttributesEvent $event)
	{
		$event->tableAttributes = $event->tableAttributes + ['recurrenceStatus'];
	}

	/**
	 * @param SetElementTableAttributeHtmlEvent $event
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\InvalidConfigException
	 */
	public static function setTableAttributeHtml(SetElementTableAttributeHtmlEvent $event)
	{

		/** @var RecurringOrderBehavior $order */
		$order = $event->sender;

		$attribute = $event->attribute;

		if ($attribute === 'recurrenceStatus')
		{
			$event->html = $order->getRecurrenceStatus()
				? Craft::$app->view->renderTemplate('recurring-orders/_cp/_statusLabel', ['status' => $order->getRecurrenceStatus()], View::TEMPLATE_MODE_CP)
				: '';
		}

		if ($attribute === 'lastRecurrence')
		{
			$event->html = $order->getLastRecurrence()
				? Craft::$app->getFormatter()->asDate($order->getLastRecurrence())
				: '';
		}

		if ($attribute === 'nextRecurrence')
		{
			$event->html = $order->getNextRecurrence()
				? Craft::$app->getFormatter()->asDate($order->getNextRecurrence())
				: '';
		}

		if ($attribute === 'recurrenceErrorCount')
		{
			$event->html = $order->getRecurrenceErrorCount();
		}

	}

	/**
	 * @param RegisterElementSourcesEvent $event
	 */
	public static function registerSources(RegisterElementSourcesEvent $event)
	{

		$sources = $event->sources;

		$sources[] = ['heading' => RecurringOrders::t("Recurring Orders")];

		$sources[] = [
			'key' => 'recurringOrders.active',
			'label' => RecurringOrders::t("_status:active"),
			'criteria' => [
				'recurrenceStatus' => 'active',
				'hasRecurrenceSchedule' => true,
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.unscheduled',
			'label' => RecurringOrders::t("_status:unscheduled"),
			'criteria' => [
				'recurrenceStatus' => 'active',
				'hasRecurrenceSchedule' => false,
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.paused',
			'label' => RecurringOrders::t("_status:paused"),
			'criteria' => [
				'recurrenceStatus' => 'paused',
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.error',
			'label' => RecurringOrders::t("_status:error"),
			'criteria' => [
				'recurrenceStatus' => 'error',
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.cancelled',
			'label' => RecurringOrders::t("_status:cancelled"),
			'criteria' => [
				'recurrenceStatus' => 'cancelled',
			],
		];

		$event->sources = $sources;

	}

}
