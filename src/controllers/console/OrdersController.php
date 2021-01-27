<?php
namespace beSteadfast\RecurringOrders\controllers\console;

use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\Db;
use beSteadfast\RecurringOrders\meta\RecurringOrder;
use beSteadfast\RecurringOrders\meta\RecurringOrderQuery;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use beSteadfast\RecurringOrders\RecurringOrders;
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
	 * @todo Should be a pass-through to a service method.
	 */
	public function actionProcessEligibleRecurrences()
	{

		/** @var RecurringOrderQuery $query */
		$query = Order::find();

		$eligibleOrders = $query->isEligibleForRecurrence()->all();

		$this->_writeLine(count($eligibleOrders) . " orders eligible for recurrence.");

		$success = true;

		foreach ($eligibleOrders as $order)
		{

			try
			{
				$this->_writeLine("Processing recurrence for Order {$order->id}...");
				RecurringOrders::getInstance()->orders->processOrderRecurrence($order);
			}
			catch (\Exception $e)
			{
				$success = false;
				$this->_writeError("Exception while processing recurrence for Order {$order->id}: " . $e->getMessage());
			}

		}

		return $success ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;

	}

	/**
	 * @return int
	 *
	 * @todo Should be a pass-through to a service method.
	 */
	public function actionProcessImminentOrders()
	{

		/** @var $query RecurringOrderQuery */
		$query = Order::find();

		try
		{
			$settings = RecurringOrders::getInstance()->getSettings();
			$cutoff = TimeHelper::fromNow($settings->imminenceInterval);
		}
		catch (\Exception $e)
		{
			$this->_writeError("The imminenceInterval setting is invalid: " . $e->getMessage());
			return ExitCode::UNSPECIFIED_ERROR;
		}

		$imminentOrders = $query->nextRecurrence('<' . $cutoff->getTimestamp())->all();
		$success = true;

		foreach ($imminentOrders as $order)
		{

			/** @var RecurringOrder $order */

			try
			{
				// TODO: Add generic "Imminent Order Observed" event.
				if (!$order->isMarkedImminent())
				{
					$order->markImminent();
				}
			}
			catch (\Exception $e)
			{
				$this->_writeError("Exception marking Order {$order->id} as Imminent: " . $e->getMessage());
				$success = false;
			}

		}

		return $success ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;

	}

}
