<?php
namespace topshelfcraft\recurringorders\controllers\console;

use topshelfcraft\recurringorders\misc\NormalizeTrait;
use yii\console\Controller;
use yii\helpers\Console;

abstract class BaseConsoleController extends Controller
{

	use NormalizeTrait;

	/**
	 * Writes an error to console
	 * @param string $msg
	 */
	protected function _writeError($msg)
	{
		$this->stderr('Error: ', Console::BOLD, Console::FG_RED);
		$this->stderr($msg . PHP_EOL);
	}

	/**
	 * @param $msg
	 */
	protected function _writeLine($msg)
	{
		$this->stderr($msg . PHP_EOL);
	}

}
