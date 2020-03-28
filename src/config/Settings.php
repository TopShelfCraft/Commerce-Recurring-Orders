<?php
namespace topshelfcraft\recurringorders\config;

use craft\base\Model;

class Settings extends Model
{

	/**
	 * @var bool
	 */
	public $addCommerceRecurringOrdersNavItem;

	/**
	 * @var bool
	 */
	public $hideCommerceSubscriptionsNavItem;

	/**
	 * @var array
	 */
	public $recurrenceIntervalOptions;

}
