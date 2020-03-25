<?php
namespace topshelfcraft\recurringorders\controllers;

trait ControllerHelpersTrait
{

	protected static function normalizeBoolean($value)
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

}
