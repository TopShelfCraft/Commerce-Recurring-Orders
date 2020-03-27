<?php
namespace topshelfcraft\recurringorders\controllers\console;

use craft\commerce\Plugin as Commerce;
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

			$success = RecurringOrders::$plugin->orders->makeOrderRecurring($order, [
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
	 * @return int
	 */
	public function actionProcessOutstandingOrders()
	{
		// TODO: Implement
		return ExitCode::UNAVAILABLE;
	}

}
