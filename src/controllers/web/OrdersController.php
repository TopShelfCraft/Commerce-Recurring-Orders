<?php
namespace topshelfcraft\recurringorders\controllers\web;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\StringHelper;
use topshelfcraft\recurringorders\meta\RecurringOrder;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\orders\RecurringOrderRecord;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * @todo Ensure GET or POST request.
 */
class OrdersController extends BaseWebController
{

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException
	 */
	public function actionProcessOrderRecurrence()
	{

		$this->requirePostRequest();

		$request = Craft::$app->request;
		$id = $request->getRequiredParam('id');

		/** @var RecurringOrder|null $order */
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
			return $this->_returnOrderEditSuccessResponse($order);
		}

		// TODO: Translate.
		return $this->returnErrorResponse(
			"Could not process this Order Recurrence."
			. ($order->getRecurrenceErrorReason() ? ' (' . $order->getRecurrenceErrorReason() . ')': '')
		);

	}

	/**
	 * @return Response
	 *
	 * @throws \yii\web\BadRequestHttpException if the Return URL is invalid.
	 */
	public function actionMakeOrderRecurring()
	{

		$request = Craft::$app->request;

		$id = $request->getRequiredParam('id');

		/** @var RecurringOrder $order */
		$order = Commerce::getInstance()->orders->getOrderById($id);

		if ($status = $request->getParam('status', RecurringOrderRecord::STATUS_ACTIVE))
		{
			$order->setRecurrenceStatus($status);
		}

		if ($recurrenceInterval = $request->getParam('recurrenceInterval'))
		{
			$order->setRecurrenceInterval($recurrenceInterval);
		}

		if ($nextRecurrence = $request->getParam('nextRecurrence'))
		{
			$order->setNextRecurrence($nextRecurrence);
		}

		if ($resetNextRecurrence = self::normalizeBoolean($request->getParam('resetNextRecurrence')))
		{
			$order->resetNextRecurrence();
		}

		try
		{
			/** @var Order $order */
			$success = Craft::$app->elements->saveElement($order);
			if ($success)
			{
				/** @var Order $order */
				return $this->_returnOrderEditSuccessResponse($order, "Recurring Order activated.");
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

		$id = $request->getRequiredParam('id');

		/** @var RecurringOrder $order */
		$order = Commerce::getInstance()->orders->getOrderById($id);

		try
		{
			/** @var Order $order */
			$success = RecurringOrders::getInstance()->orders->replicateAsRecurring($order);
			if ($success)
			{
				return $this->_returnOrderEditSuccessResponse($order);
			}
		}
		catch(\Throwable $e)
		{
			return $this->returnErrorResponse($e->getMessage());
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Something went wrong while replicating the order: " . $order->getRecurrenceErrorReason());

	}

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException if `id` param is not provided.
	 */
	public function actionPauseRecurringOrder()
	{
		// TODO: Translate
		return $this->_actionUpdateWithStatus(RecurringOrderRecord::STATUS_PAUSED, "Recurring Order paused.");
	}

	/**
	 * @return Response|null
	 *
	 * @throws \yii\web\BadRequestHttpException if `id` param is not provided.
	 */
	public function actionCancelRecurringOrder()
	{
		// TODO: Translate.
		return $this->_actionUpdateWithStatus(RecurringOrderRecord::STATUS_CANCELLED, "Recurring Order cancelled.");
	}

	/**
	 * @return Order
	 *
	 * @throws BadRequestHttpException
	 */
	private function _getRequiredRequestOrder()
	{
		$id = Craft::$app->request->getRequiredParam('id');
		$order = Commerce::getInstance()->orders->getOrderById($id);
		if ($order)
		{
			return $order;
		}
		throw new BadRequestHttpException('Could not identify an Order from the request parameters.');
	}

	/**
	 * @param $status
	 * @param string $successMessage
	 *
	 * @return Response|null
	 *
	 * @throws BadRequestHttpException if the request doesn't specify a valid Order.
	 * @throws \Throwable if reasons
	 */
	private function _actionUpdateWithStatus($status, $successMessage = null)
	{

		$order = $this->_getRequiredRequestOrder();

		/** @var RecurringOrder $order */
		$order->setRecurrenceStatus($status);

		/** @var Order $order */
		if (Craft::$app->elements->saveElement($order))
		{
			return $this->_returnOrderEditSuccessResponse($order, $successMessage);
		}

		// TODO: Translate.
		return $this->returnErrorResponse("Could not update the Order.");

	}

	/**
	 * @param Order $order
	 * @param array $jsonParams
	 *
	 * @return Response
	 *
	 * @throws \yii\base\InvalidConfigException from `getPathInfo()` if the path info cannot be determined due to unexpected server configuration
	 * @throws \yii\web\BadRequestHttpException from `redirectToPostedUrl()` if the redirect param was tampered with.
	 */
	private function _returnOrderEditSuccessResponse(Order $order, $flashMessage = null)
	{

		$request = Craft::$app->request;
		$defaultRedirectUrl = $request->isGet ? $request->getReferrer() : $request->getPathInfo();

		if (StringHelper::contains($defaultRedirectUrl, $order->getCpEditUrl(), false))
		{
			if (!empty($flashMessage))
			{
				Craft::$app->session->setNotice($flashMessage);
			}
			$defaultRedirectUrl .= '#recurringOrdersTab';
		}

		return $this->returnSuccessResponse($order, ['orderId' => $order->id], $defaultRedirectUrl);

	}

}
