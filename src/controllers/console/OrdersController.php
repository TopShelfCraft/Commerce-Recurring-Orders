<?php
namespace topshelfcraft\recurringorders\controllers\console;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use topshelfcraft\recurringorders\meta\RecurringOrder;
use topshelfcraft\recurringorders\meta\RecurringOrderQuery;
use topshelfcraft\recurringorders\misc\TimeHelper;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\console\ExitCode;

/**
 * Order functions
 */
class OrdersController extends BaseConsoleController
{

	/**
	 * @param $orderId
	 * @param $recurrenceInterval
	 * @param $resetNextRecurrence
	 *
	 * @return int
	 */
	public function actionMakeOrderRecurring($orderId, $recurrenceInterval, $resetNextRecurrence = false)
	{

		$resetNextRecurrence = self::normalizeBoolean($resetNextRecurrence);

		try
		{

			$order = Commerce::getInstance()->orders->getOrderById($orderId);

			$success = RecurringOrders::getInstance()->orders->makeOrderRecurring($order, [
				'recurrenceInterval' => $recurrenceInterval
			], $resetNextRecurrence);

			if ($success)
			{
				return ExitCode::OK;
			}

		}
		catch(\Exception $e)
		{
			$this->_writeError($e->getMessage());
		}

		// TODO: Translate?
		$this->_writeError("Could not make the Order recurring.");
		return ExitCode::UNSPECIFIED_ERROR;

	}

	/**
	 * @param $orderId
	 *
	 * @return int
	 *
	 * @throws \yii\base\Exception
	 */
	public function actionProcessOrderRecurrence($orderId)
	{

		$order = Commerce::getInstance()->orders->getOrderById($orderId);

		if (!$order)
		{
			return ExitCode::UNSPECIFIED_ERROR;
		}

		try
		{
			$success = RecurringOrders::getInstance()->orders->processOrderRecurrence($order);
			return $success ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
		}
		catch (\Exception $e)
		{
			$this->_writeError($e->getMessage());
		}

		return ExitCode::UNSPECIFIED_ERROR;

	}

	/**
	 * @return int
	 *
	 * @todo Should probably be a pass-through to a service method.
	 */
	public function actionProcessOutstandingOrders()
	{

		/** @var RecurringOrderQuery $query */
		$query = Order::find();

		$outstandingOrders = $query->isOutstanding()->all();

		$success = true;

		foreach ($outstandingOrders as $order)
		{

			try
			{
				$success = RecurringOrders::getInstance()->orders->processOrderRecurrence($order) && $success;
			}
			catch (\Exception $e)
			{
				$this->_writeError("Error while processing recurrence for Order {$order->id}: " . $e->getMessage());
			}

		}

		return $success ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;

	}

}
