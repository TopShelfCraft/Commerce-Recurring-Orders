<?php
namespace topshelfcraft\recurringorders\controllers\console;

use craft\helpers\DateTimeHelper;
use topshelfcraft\recurringorders\misc\IntervalHelper;
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
				IntervalHelper::normalizeInterval($interval)
			)
		);
		return ExitCode::OK;
	}

}
