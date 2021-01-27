<?php
namespace beSteadfast\RecurringOrders\controllers\console;

use Craft;
use craft\commerce\elements\Order;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use beSteadfast\RecurringOrders\meta\RecurringOrder;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use yii\console\ExitCode;

/**
 * Development & Testing functions
 */
class DevController extends BaseConsoleController
{

	/**
	 * @return int
	 *
	 * @throws \Exception
	 */
	public function actionTestInterval($interval)
	{
		$this->_writeLine(
			DateTimeHelper::humanDurationFromInterval(
				TimeHelper::normalizeInterval($interval)
			)
		);
		return ExitCode::OK;

	}

	/**
	 * @param $orderId
	 *
	 * @return int
	 *
	 * @throws \yii\db\Exception if Record cannot be saved.
	 */
	public function actionMarkOrderImminent($orderId)
	{

		$order = Order::findOne($orderId);

		if (!$order)
		{
			$this->_writeError("Order not found.");
			return ExitCode::UNSPECIFIED_ERROR;
		}

		/** @var RecurringOrder $order **/
		$success = $order->markImminent();

		if ($success)
		{
			$this->_writeLine("Order marked Imminent. ✅");
			return ExitCode::OK;
		}

		$this->_writeError("Order NOT marked Imminent. 🚫️");
		return ExitCode::UNSPECIFIED_ERROR;

	}


	/**
	 * @return int
	 *
	 * @throws \Throwable
	 */
	public function actionNuke()
	{

		$this->stdout(PHP_EOL);

		$forRealz = $this->confirm('Delete ALL Orders (Recurring and otherwise) and run aggressive Garbage Collection?');

		if (!$forRealz)
		{
			return ExitCode::OK;
		}

		$this->stdout(PHP_EOL);

		$this->stdout('Deleting all Orders... ', Console::FG_YELLOW);
		foreach (Order::findAll() as $order)
		{
			Craft::$app->elements->deleteElement($order);
		}
		$this->stdout('✅' . PHP_EOL);

		$gc = Craft::$app->getGc();

		$previousDeleteAllTrashed = $gc->deleteAllTrashed;
		$gc->deleteAllTrashed = true;

		$this->stdout('Running garbage collection... ', Console::FG_YELLOW);
		$gc->run(true);
		$this->stdout('✅' . PHP_EOL);

		$this->stdout(PHP_EOL);

		$gc->deleteAllTrashed = $previousDeleteAllTrashed;


		return ExitCode::OK;

	}

}
