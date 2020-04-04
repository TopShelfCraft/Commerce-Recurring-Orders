<?php
namespace topshelfcraft\recurringorders\controllers\web;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\DateTimeHelper;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\orders\RecurringOrderRecord;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\web\Response;

class OrdersController extends BaseWebController
{

	public function actionProcessOutstandingOrders()
	{
		// TODO: Implement
		// TODO: Translate.
		return $this->returnErrorResponse("Not yet implemented.");
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
			$success = RecurringOrders::$plugin->orders->makeOrderRecurring($order, $attributes, $resetNextRecurrence);
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

}
