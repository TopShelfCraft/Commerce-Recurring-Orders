<?php
namespace topshelfcraft\recurringorders\view;

use Craft;
use topshelfcraft\recurringorders\RecurringOrders;

class CpHelper
{

	/**
	 * Optionally remove the Subscriptions subnav link from the Commerce plugin's nav item.
	 *
	 * @param $navItems
	 *
	 * @return array
	 */
	public static function modifyCpNavItems($navItems)
	{

		$settings = RecurringOrders::getInstance()->getSettings();

		$commerceItemKey = array_search('commerce', array_column($navItems, 'url'));
		$commerceItem = $navItems[$commerceItemKey];

		if ($settings->hideCommerceSubscriptionsNavItem)
		{
			unset($commerceItem['subnav']['subscriptions']);
		}

		if ($settings->addCommerceRecurringOrdersNavItem)
		{

			function _withValueInsertedAfterKey($newVal, $newKey, $array, $key)
			{
				$new = [];
				foreach ($array as $k => $v) {
					$new[$k] = $v;
					if ($k === $key) {
						$new[$newKey] = $newVal;
					}
				}
				return $new;
			}

			if (Craft::$app->getUser()->checkPermission('accessPlugin-' . RecurringOrders::$plugin->id)) {
				$newItem = [
					'label' => RecurringOrders::t('Recurring Orders'),
					'url' => 'recurring-orders'
				];
				$commerceItem['subnav'] = _withValueInsertedAfterKey($newItem, 'recurring-orders', $commerceItem['subnav'], 'orders');
			}

		}

		$navItems[$commerceItemKey] = $commerceItem;

		return $navItems;

	}

}
