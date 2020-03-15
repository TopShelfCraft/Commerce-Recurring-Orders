<?php
namespace topshelfcraft\recurringorders\controllers\console;

use yii\console\Controller;
use yii\helpers\Console;

abstract class BaseConsoleController extends Controller
{

	/**
	 * Writes an error to console
	 * @param string $msg
	 */
	protected function _writeError($msg)
	{
		$this->stderr('Error: ', Console::BOLD, Console::FG_RED);
		$this->stderr($msg . PHP_EOL);
	}

}
