<?php
namespace topshelfcraft\recurringorders\config;

use craft\base\Model;

class Settings extends Model
{

	/**
	 * @var bool
	 */
	public $addOrderElementSources = true;

	/**
	 * @var bool
	 */
	public $hideCommerceSubscriptionsNavItem = false;

	/**
	 * @var array
	 */
	public $recurrenceIntervalOptions;

	/**
	 * @var bool
	 */
	public $showUserOrdersTab = true;

	/**
	 * @var bool
	 */
	public $showOrderHistoryTab = true;

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
