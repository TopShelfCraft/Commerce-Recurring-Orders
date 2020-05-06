<?php
namespace topshelfcraft\recurringorders\controllers\web;

use Craft;
use craft\base\Element;
use craft\commerce\base\Gateway;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\orders\RecurringOrderRecord;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\Exception;
use yii\web\Response;

class OrdersController extends BaseWebController
{

	protected $allowAnonymous = [
		'add-payment-source'
	];

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionProcessOrderRecurrence()
	{

		$request = Craft::$app->request;

		$id = $request->getRequiredParam('id');
		$order = Commerce::getInstance()->orders->getOrderById($id);

		if (!$order)
		{
			// TODO: Translate.
			return $this->returnErrorResponse("Could not process this order recurrence, because the Parent Order does not exist.");
		}

		try
		{
			$success = RecurringOrders::getInstance()->orders->processOrderRecurrence($order);
		}
		catch(\Exception $e)
		{
			$success = false;
		}

		if ($success)
		{
			return $this->returnSuccessResponse();
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Could not process this Order Recurrence.");

	}

	/**
	 * @return Response
	 *
	 * @throws \yii\web\BadRequestHttpException if the Return URL is invalid.
	 */
	public function actionMakeOrderRecurring()
	{

		$request = Craft::$app->request;
		$attributes = [];

		$id = $request->getRequiredParam('id');

		/** @var RecurringOrderBehavior $order */
		$order = Commerce::getInstance()->orders->getOrderById($id);

		if ($status = $request->getParam('status'))
		{
			$attributes['status'] = $status;
		}

		if ($recurrenceInterval = $request->getParam('recurrenceInterval'))
		{
			$attributes['recurrenceInterval'] = $recurrenceInterval;
		}

		if ($nextRecurrence = $request->getParam('nextRecurrence'))
		{
			$attributes['nextRecurrence'] = DateTimeHelper::toDateTime($nextRecurrence);
		}

		$resetNextRecurrence = self::normalizeBoolean(
			$request->getParam('resetNextRecurrence', false)
		);

		try
		{
			/** @var Order $order */
			$success = RecurringOrders::getInstance()->orders->makeOrderRecurring($order, $attributes, $resetNextRecurrence);
			if ($success)
			{
				return $this->returnSuccessResponse();
			}
		}
		catch(\Exception $e)
		{
			return $this->returnErrorResponse($e->getMessage());
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Could not make Order recurring.");

	}

	public function actionReplicateAsRecurring()
	{

		$request = Craft::$app->request;
		$attributes = [];

		$id = $request->getRequiredParam('id');

		/** @var RecurringOrderBehavior $order */
		$order = Commerce::getInstance()->orders->getOrderById($id);

		try
		{
			/** @var Order $order */
			$success = RecurringOrders::getInstance()->orders->replicateAsRecurring($order);
			if ($success)
			{
				return $this->returnSuccessResponse();
			}
		}
		catch(\Throwable $e)
		{
			return $this->returnErrorResponse($e->getMessage());
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Something went wrong while replicating the order.");

	}

	/**
	 * @return Response|null
	 *
	 * @throws \yii\base\InvalidConfigException
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionAddPaymentSource()
	{

		$request = Craft::$app->getRequest();
		$commerce = Commerce::getInstance();

		/*
		 * Identify the  Order
		 */

		$orderId = $request->getRequiredBodyParam('orderId');
		$order = $commerce->orders->getOrderById($orderId);

		if (!$order)
		{
			// TODO: Translate
			return $this->returnErrorResponse("Can't find an Order by that ID.");
		}

		/*
		 * Identify the Gateway
		 */

		$gatewayId = $request->getBodyParam('gatewayId') ?? $order->gatewayId;

		/** @var Gateway $gateway */
		$gateway = $commerce->gateways->getGatewayById($gatewayId);

		if (!$gateway || !$gateway->supportsPaymentSources()) {

			$error = Commerce::t('There is no gateway selected that supports payment sources.');
			return $this->returnErrorResponse($error);
		}

		/*
		 * Ensure a User
		 */

		try
		{
			$user = $this->_ensureOrderUser($order);
		}
		catch (\Exception $e)
		{
			RecurringOrders::error($e->getMessage());
			return $this->returnErrorResponse($e->getMessage());
		}

		/*
		 * Process the payment form
		 */

		// Get the payment method' gateway adapter's expected form model
		$paymentForm = $gateway->getPaymentFormModel();
		$paymentForm->setAttributes($request->getBodyParams(), false);
		$description = (string)$request->getBodyParam('description');

		$transaction = Craft::$app->db->getTransaction() ?? Craft::$app->db->beginTransaction();

		try
		{
			$paymentSource = $commerce->paymentSources->createPaymentSource($user->id, $gateway, $paymentForm, $description);
			$order->paymentSourceId = $paymentSource->id;
			Craft::$app->elements->saveElement($order);
			$transaction->commit();
		}
		catch (\Throwable $e) {

			Craft::$app->getErrorHandler()->logException($e);
			RecurringOrders::error($e->getMessage());

			$transaction->rollBack();

			return $this->returnErrorResponse(Commerce::t('Could not create the payment source.'));

		}

		return $this->returnSuccessResponse(null, ['paymentSource' => $paymentSource]);

	}

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException if `id` param is not provided.
	 */
	public function actionPauseRecurringOrder()
	{
		return $this->_actionUpdateWithStatus(RecurringOrderRecord::STATUS_PAUSED);
	}

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException if `id` param is not provided.
	 */
	public function actionCancelRecurringOrder()
	{
		return $this->_actionUpdateWithStatus(RecurringOrderRecord::STATUS_CANCELLED);
	}

	/**
	 * @param $status
	 *
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException if `id` param is not provided.
	 */
	private function _actionUpdateWithStatus($status)
	{

		$request = Craft::$app->request;

		$id = $request->getRequiredParam('id');

		try
		{

			$recurringOrder = RecurringOrderRecord::findOne($id);

			if (!$recurringOrder)
			{
				// TODO: Translate.
				return $this->returnErrorResponse("Order is not Recurring.");
			}

			$recurringOrder->status = $status;
			$success = $recurringOrder->save();

		}
		catch(\Exception $e)
		{
			$success = false;
		}

		if ($success)
		{
			return $this->returnSuccessResponse();
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Could not update the Order.");

	}

	/**
	 * @param Order $order
	 *
	 * @return User
	 *
	 */
	public function _ensureOrderUser(Order $order)
	{

		// Do we already have a User?
		if ($order->getUser())
		{
			return $order->getUser();
		}

		// Can't create a user without an email
		if (!$order->email)
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
		$user = User::find()->email($order->email)->status(null)->one();
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
			RecurringOrders::error("Could not create a User to save Payment Source on Order.");
			$errors = $user->getErrors();
			RecurringOrders::error($errors);
			throw new Exception("Could not create a User to save Payment Source on Order.");
		}

		return $user;

	}

}
