<?php
namespace topshelfcraft\recurringorders\web\cp;

use Craft;
use craft\base\Component;
use craft\commerce\Plugin as Commerce;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterElementDefaultTableAttributesEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementSourcesEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;
use craft\web\View;
use topshelfcraft\recurringorders\meta\RecurringOrder;
use topshelfcraft\recurringorders\RecurringOrders;
use topshelfcraft\recurringorders\web\widgets\CountAllRecurringOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\CountGeneratedOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\RecentGeneratedOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\RecentRecurringOrdersWidget;
use topshelfcraft\recurringorders\web\widgets\CountUpcomingRecurrencesWidget;
use yii\base\Exception;

class CpCustomizations extends Component
{

	/**
	 * Optionally remove the Subscriptions subnav link from the Commerce plugin's nav item.
	 *
	 * @param RegisterCpNavItemsEvent $event
	 */
	public function handleModifyCpNavItems(RegisterCpNavItemsEvent $event)
	{

		$settings = RecurringOrders::getInstance()->getSettings();

		$navItems = $event->navItems;

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

		$event->navItems = $navItems;

	}

	/**
	 * @param RegisterElementSourcesEvent $event
	 */
	public function handleRegisterSources(RegisterElementSourcesEvent $event)
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
			'label' => RecurringOrders::t("Generated Orders"),
			'criteria' => [
				'hasParentOrder' => true,
			],
		];

		$event->sources = $sources;

	}

	/**
	 * @param RegisterElementSortOptionsEvent $event
	 */
	public function handleRegisterSortOptions(RegisterElementSortOptionsEvent $event)
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
	public function handleRegisterTableAttributes(RegisterElementTableAttributesEvent $event)
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
	public function handleRegisterDefaultTableAttributes(RegisterElementDefaultTableAttributesEvent $event)
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
	public function handleSetTableAttributeHtml(SetElementTableAttributeHtmlEvent $event)
	{

		/** @var RecurringOrder $order */
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
	public function handleRegisterWidgetTypes(RegisterComponentTypesEvent $event)
	{
		$event->types[] = RecentGeneratedOrdersWidget::class;
		$event->types[] = RecentRecurringOrdersWidget::class;
		$event->types[] = CountAllRecurringOrdersWidget::class;
		$event->types[] = CountGeneratedOrdersWidget::class;
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

		$context['tabs'][] = [
			'label' => RecurringOrders::t('Recurring Orders'),
			'url' => '#recurringOrdersTab',
			'class' => null,
		];

		// Add supplemental info to Order screen titles
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
		return Craft::$app->view->renderTemplate('recurring-orders/cp/_hooks/cp.commerce.order.edit.main-pane', $context);
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

		if (!RecurringOrders::getInstance()->getSettings()->showUserRecurringOrdersTab)
		{
			return;
		}

		$currentUser = Craft::$app->getUser()->getIdentity();

		if ($context['isNewUser'] || !$currentUser->can('commerce-manageOrders'))
		{
			return;
		}

		$context['tabs']['recurringOrders'] = [
			'label' => RecurringOrders::t('Recurring Orders'),
			'url' => '#recurringOrders'
		];

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

		if (!RecurringOrders::getInstance()->getSettings()->showUserRecurringOrdersTab)
		{
			return;
		}

		$currentUser = Craft::$app->getUser()->getIdentity();

		if (!$context['user'] || $context['isNewUser'] || !$currentUser->can('commerce-manageOrders'))
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
