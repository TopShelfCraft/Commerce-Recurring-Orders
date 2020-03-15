<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\Exception;

class Orders extends Component
{

	/**
	 * @param Order $order The Commerce Order
	 * @param array $attributes Additional attributes to set on the Recurring Order record
	 * @param bool $resetNextRecurrence Whether the Next Occurrence should be reset from the present time (if possible).
	 *
	 * @return bool
	 *
	 * @throws \Exception
	 */
	public function makeOrderRecurring(Order $order, $attributes, $resetNextRecurrence = false)
	{

		$record = $order->getRecurringOrder() ?: new RecurringOrderRecord([
			'id' => $order->id,
		]);

		// Default status, if none provided
		if (!isset($attributes['status']))
		{
			$attributes['status'] = RecurringOrderRecord::STATUS_ACTIVE;
		}

		$record->setAttributes($attributes, false);

		if ($resetNextRecurrence && $record->recurrenceInterval)
		{
			$record->nextRecurrence = (new \DateTime())->add(OrdersHelper::normalizeInterval($record->recurrenceInterval));
		}

		if ($success = $record->save())
		{
			$order->loadRecurringOrderRecord($record);
		}

		return $success;

	}

	/**
	 * Intercepts Order Save events and, if it appears we're in the middle of an Action request,
	 * processes any given `makeRecurring` attributes.
	 *
	 * @param Order $order
	 *
	 * @return void
	 *
	 * @throws \Exception if `recurrenceInterval` is not valid.
	 */
	public function afterSaveOrder(Order $order)
	{

		$request = Craft::$app->request;

		if (!$request->getIsActionRequest() || !$request->getParam('makeRecurring'))
		{
			return;
		}

		$attributes = [];

		if ($status = $request->getParam('makeRecurring.status'))
		{
			$attributes['status'] = $status;
		}

		if ($recurrenceInterval = $request->getParam('makeRecurring.recurrenceInterval'))
		{
			$attributes['recurrenceInterval'] = $recurrenceInterval;
		}

		if ($nextRecurrence = $request->getParam('makeRecurring.nextRecurrence'))
		{
			$attributes['nextRecurrence'] = $nextRecurrence;
		}

		$this->makeOrderRecurring($order, $attributes);

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

}
