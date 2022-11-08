<?php
namespace TopShelfCraft\RecurringOrders\config;

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
	 * @var bool
	 */
	public $hideCommerceSubscriptionsCustomerTables = false;

	/**
	 * @var bool
	 */
	public $hideRecurrenceControlsForGeneratedOrders = true;

	/**
	 * @var bool
	 */
	public $hideRecurrenceControlsForNonRecurringOrders = false;

	/**
	 * @var bool
	 */
	public $hideRecurrenceControlsForOriginatingOrders = true;

	/**
	 * @var string|int
	 */
	public $imminenceInterval = 'P1W';

	/**
	 * @var int|null
	 */
	public $maxRecurrenceErrorCount = null;

	/**
	 * @var array
	 */
	public $recurrenceIntervalOptions;

	/**
	 * @var string|int
	 */
	public $retryInterval = 'P1D';

	/**
	 *
	 */
	public $showUserPaymentSourcesTab = true;

	/**
	 * @var bool
	 */
	public $showUserRecurringOrdersTab = true;

	/**
	 * @param bool $addBlankOption
	 *
	 * @return array
	 */
	public function getRecurrenceIntervalOptions($addBlankOption = true)
	{
		return $addBlankOption ? array_merge(['' => ''], $this->recurrenceIntervalOptions) : $this->recurrenceIntervalOptions;
	}

}
