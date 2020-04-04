<?php
namespace topshelfcraft\recurringorders\misc;

trait NormalizeTrait
{

	protected static function normalizeBoolean($value)
	{
		return filter_var($value, FILTER_VALIDATE_BOOLEAN);
	}

}
