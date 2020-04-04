<?php
namespace topshelfcraft\recurringorders\view;

use Craft;
use craft\events\RegisterElementDefaultTableAttributesEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\web\View;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\RecurringOrders;
use yii\base\Exception;

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

		/*
		 * Once upon a time there was the ability to *replace* the Commerce "Subscriptions" nav link
		 * with a link to our own CP page. But then we didn't have a CP page. Still, this little snippet is far too
		 * clever to part with just yet. Maybe someday it'll be useful again. For now it's just here collecting dust.
		 * :-D
		 */
		if (false)
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

	/**
	 * @param RegisterElementSourcesEvent $event
	 */
	public static function registerSources(RegisterElementSourcesEvent $event)
	{

		$sources = $event->sources;

		$sources[] = ['heading' => RecurringOrders::t("Recurring Orders")];

		$sources[] = [
			'key' => 'recurringOrders.active',
			'label' => RecurringOrders::t("_status:active"),
			'criteria' => [
				'recurrenceStatus' => 'active',
				'hasRecurrenceSchedule' => true,
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.unscheduled',
			'label' => RecurringOrders::t("_status:unscheduled"),
			'criteria' => [
				'recurrenceStatus' => 'active',
				'hasRecurrenceSchedule' => false,
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.paused',
			'label' => RecurringOrders::t("_status:paused"),
			'criteria' => [
				'recurrenceStatus' => 'paused',
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.error',
			'label' => RecurringOrders::t("_status:error"),
			'criteria' => [
				'recurrenceStatus' => 'error',
			],
		];

		$sources[] = [
			'key' => 'recurringOrders.cancelled',
			'label' => RecurringOrders::t("_status:cancelled"),
			'criteria' => [
				'recurrenceStatus' => 'cancelled',
			],
		];

		$event->sources = $sources;

	}

	/**
	 * @param RegisterElementSortOptionsEvent $event
	 */
	public static function registerSortOptions(RegisterElementSortOptionsEvent $event)
	{
		$event->sortOptions = $event->sortOptions + [
				'recurringOrders.status' => RecurringOrders::t('Recurrence Status'),
				'recurringOrders.lastRecurrence' => RecurringOrders::t('Last Recurrence'),
				'recurringOrders.nextRecurrence' => RecurringOrders::t('Next Recurrence'),
				'recurringOrders.errorCount' => RecurringOrders::t('Recurrence Error Count'),
			];
	}

	/**
	 * @param RegisterElementTableAttributesEvent $event
	 */
	public static function registerTableAttributes(RegisterElementTableAttributesEvent $event)
	{
		$event->tableAttributes = $event->tableAttributes + [
				'recurrenceStatus' => RecurringOrders::t('Recurrence Status'),
				'lastRecurrence' => RecurringOrders::t('Last Recurrence'),
				'nextRecurrence' => RecurringOrders::t('Next Recurrence'),
				'recurrenceErrorCount' => RecurringOrders::t('Recurrence Error Count'),
				'originatingOrder' => RecurringOrders::t('Originating Order'),
				'parentOrder' => RecurringOrders::t('Parent Order'),
			];
	}

	/**
	 * @param RegisterElementDefaultTableAttributesEvent $event
	 */
	public static function registerDefaultTableAttributes(RegisterElementDefaultTableAttributesEvent $event)
	{
		$event->tableAttributes = $event->tableAttributes + ['recurrenceStatus'];
	}

	/**
	 * @param SetElementTableAttributeHtmlEvent $event
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 * @throws \yii\base\InvalidConfigException
	 */
	public static function setTableAttributeHtml(SetElementTableAttributeHtmlEvent $event)
	{

		/** @var RecurringOrderBehavior $order */
		$order = $event->sender;

		$attribute = $event->attribute;

		if ($attribute === 'recurrenceStatus') {
			$event->html = $order->getRecurrenceStatus()
				? Craft::$app->view->renderTemplate('recurring-orders/_cp/_statusLabel', ['status' => $order->getRecurrenceStatus()], View::TEMPLATE_MODE_CP)
				: '';
		}

		if ($attribute === 'lastRecurrence') {
			$event->html = $order->getLastRecurrence()
				? Craft::$app->getFormatter()->asDate($order->getLastRecurrence())
				: '';
		}

		if ($attribute === 'nextRecurrence') {
			$event->html = $order->getNextRecurrence()
				? Craft::$app->getFormatter()->asDate($order->getNextRecurrence())
				: '';
		}

		if ($attribute === 'recurrenceErrorCount') {
			$event->html = $order->getRecurrenceErrorCount();
		}

		if ($attribute === 'originatingOrder') {
			// TODO: Eager load Originating orders when this table attribute is active
			$event->html = $order->getOriginatingOrderId() ? $order->getOriginatingOrder()->getLink() : '';
		}

		if ($attribute === 'parentOrder') {
			// TODO: Eager-load Parent orders when this table attribute is active
			$event->html = $order->getParentOrderId() ? $order->getParentOrder()->getLink() : '';
		}

	}

}
