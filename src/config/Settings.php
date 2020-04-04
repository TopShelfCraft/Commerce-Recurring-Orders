<?php
namespace topshelfcraft\recurringorders\config;

use craft\base\Model;

class Settings extends Model
{

	/**
	 * @var bool
	 */
	public $hideCommerceSubscriptionsNavItem;

	/**
	 * @var array
	 */
	public $recurrenceIntervalOptions;

	/**
	 * @param bool $addBlankOption
	 *
	 * @return array
	 */
	public function getRecurrenceIntervalOptions($addBlankOption = true)
	{
		return array_merge(['' => ''], $this->recurrenceIntervalOptions);
	}

}
