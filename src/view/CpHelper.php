<?php
namespace topshelfcraft\recurringorders\view;

use Craft;
use craft\commerce\Plugin as Commerce;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterElementDefaultTableAttributesEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\web\View;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\RecurringOrders;
use topshelfcraft\recurringorders\web\widgets\RecentGeneratedOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\RecentRecurringOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\CountUpcomingRecurrencesWidget;
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

			if (Craft::$app->getUser()->checkPermission('accessPlugin-' . RecurringOrders::getInstance()->id)) {
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

		if (!RecurringOrders::getInstance()->getSettings()->addOrderElementSources)
		{
			return;
		}

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

		$sources[] = [
			'key' => 'recurringOrders.generated',
			'label' => RecurringOrders::t("Generated Recurrences"),
			'criteria' => [
				'hasParentOrder' => true,
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
				? Craft::$app->view->renderTemplate('recurring-orders/cp/_includes/_statusLabel', ['status' => $order->getRecurrenceStatus()], View::TEMPLATE_MODE_CP)
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

	/**
	 * @param RegisterComponentTypesEvent $event
	 */
	public static function registerWidgetTypes(RegisterComponentTypesEvent $event)
	{
		$event->types[] = RecentGeneratedOrdersWidget::class;
		$event->types[] = RecentRecurringOrdersWidget::class;
		$event->types[] = CountUpcomingRecurrencesWidget::class;
	}

	/**
	 * Optionally hides Commerce's Subscriptions from the Customer Info tab on Customer and User pages.
	 *
	 * @param array $context
	 *
	 * @return string
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public static function cpLayoutsBaseHook(array &$context)
	{

		if (RecurringOrders::getInstance()->getSettings()->hideCommerceSubscriptionsCustomerTables)
		{
			return Craft::$app->view->renderTemplate('recurring-orders/cp/_hooks/cp.layouts.base', $context);
		}

	}

	/**
	 * Adds a Recurring Orders tab on the Users edit screen.
	 *
	 * @param array $context
	 *
	 * @return string
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public static function cpCommerceOrderEditHook(array &$context)
	{

		if ($context['order']->isCompleted)
		{
			$context['tabs'][] = [
				'label' => RecurringOrders::t('Recurring Orders'),
				'url' => '#recurringOrdersTab',
				'class' => null,
			];
		}

		return Craft::$app->view->renderTemplate('recurring-orders/cp/_hooks/cp.commerce.order.edit', $context);

	}

	/**
	 * Renders the content for the Recurring Orders tab on the Order edit screen.
	 * @param array $context
	 *
	 * @return string
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public static function cpCommerceOrderEditMainPaneHook(array &$context)
	{

		$return = "</div>"; // Usurp the container from Commerce's existing Order Details tab.
		$return .= Craft::$app->view->renderTemplate('recurring-orders/cp/_hooks/cp.commerce.order.edit.main-pane', $context);
		$return .= "<div>"; // Mend our earlier usurpation by restoring order and symmetry to the HTML.

		return $return;

	}

	/**
	 * Optionally adds a Recurring Orders tab on the Users edit screen.
	 *
	 * @param array $context
	 *
	 * @return string
	 */
	public static function cpUsersEditHook(array &$context)
	{

		if (RecurringOrders::getInstance()->getSettings()->showUserRecurringOrdersTab)
		{

			$currentUser = Craft::$app->getUser()->getIdentity();

			if (!$context['isNewUser'] && $currentUser->can('commerce-manageOrders'))
			{
				$context['tabs']['recurringOrders'] = [
					'label' => RecurringOrders::t('Recurring Orders'),
					'url' => '#recurringOrders'
				];
			}

		}

	}

	/**
	 * Fills in the content for the Recurring Orders tab on the Users edit screen.
	 *
	 * @param array $context
	 *
	 * @return string
	 *
	 * @throws Exception
	 * @throws \Twig\Error\LoaderError
	 * @throws \Twig\Error\RuntimeError
	 * @throws \Twig\Error\SyntaxError
	 */
	public static function cpUsersEditContentHook(array &$context)
	{

		if (!$context['user'] || $context['isNewUser'])
		{
			return;
		}

		$customer = Commerce::getInstance()->getCustomers()->getCustomerByUserId($context['user']->id);

		if (!$customer) {
			return;
		}

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/_hooks/cp.users.edit.content', [
			'customer' => $customer,
		]);

	}

}
