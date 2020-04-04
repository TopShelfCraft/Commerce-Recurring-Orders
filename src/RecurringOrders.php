<?php
namespace topshelfcraft\recurringorders;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\console\Application as ConsoleApplication;
use craft\events\ElementEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\helpers\FileHelper;
use craft\services\Elements;
use craft\web\Application as WebApplication;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use topshelfcraft\recurringorders\config\Settings;
use topshelfcraft\recurringorders\orders\Orders;
use topshelfcraft\recurringorders\orders\RecurringOrderBehavior;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;
use topshelfcraft\recurringorders\view\CpHelper;
use yii\base\Event;

/**
 * Module to encapsulate Recurring Orders functionality.
 *
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('recurring-orders')`
 *
 * @see http://www.yiiframework.com/doc-2.0/guide-structure-modules.html
 *
 * @property Orders $orders
 *
 * @method Settings getSettings()
 *
 * @todo: Translate all the things!
 */
class RecurringOrders extends Plugin
{

	/*
     * Static properties
     * ===========================================================================
     */

	/**
	 * @var RecurringOrders $plugin
	 */
	public static $plugin;

	/*
     * Public methods
     * ===========================================================================
     */

	public function __construct($id, $parent = null, array $config = [])
	{

		/*
		 * We register our components here, rather than utilizing the "extra" field of our `composer.json`
		 * because this way, we don't have to re-run Composer every time we add a service or something.
		 */

		$config['components'] = [
			'orders' => Orders::class,
		];

		parent::__construct($id, $parent, $config);

	}

	/**
	 * Initializes the module.
	 */
	public function init()
	{

		parent::init();
		self::$plugin = $this;

		$this->_attachComponentBehaviors();
		$this->_registerEventHandlers();
		$this->_attachVariableGlobal();
		$this->_registerTemplateHooks();

		// Register controllers via namespace map

		if (Craft::$app instanceof ConsoleApplication)
		{
			$this->controllerNamespace = 'topshelfcraft\\recurringorders\\controllers\\console';
		}
		if (Craft::$app instanceof WebApplication)
		{
			$this->controllerNamespace = 'topshelfcraft\\recurringorders\\controllers\\web';
		}

	}

	/**
	 * @param $msg
	 * @param string $level
	 * @param string $file
	 */
	public static function log($msg, $level = 'notice', $file = 'RecurringOrders')
	{
		try
		{
			$file = Craft::getAlias('@storage/logs/' . $file . '.log');
			$log = "\n" . date('Y-m-d H:i:s') . " [{$level}]" . "\n" . print_r($msg, true);
			FileHelper::writeToFile($file, $log, ['append' => true]);
		}
		catch(\Exception $e)
		{
			Craft::error($e->getMessage());
		}
	}

	/**
	 * @param $msg
	 * @param string $level
	 * @param string $file
	 */
	public static function error($msg, $level = 'error', $file = 'RecurringOrders')
	{
		static::log($msg, $level, $file);
	}

	/**
	 * @param $message
	 * @param array $params
	 * @param null $language
	 *
	 * @return string
	 */
	public static function t($message, $params = [], $language = null)
	{
		return Craft::t(self::getInstance()->getHandle(), $message, $params, $language);
	}

	/*
     * Protected methods
     * ===========================================================================
     */

	/**
	 * Creates and returns the model used to store the plugin’s settings.
	 *
	 * @return Settings|null
	 */
	protected function createSettingsModel()
	{
		return new Settings();
	}

	/*
     * Private methods
     * ===========================================================================
     */

	/**
	 * Makes the plugin instance available to Twig via the `craft.recurringOrders` variable.
	 */
	private function _attachVariableGlobal() {

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			function (Event $event) {
				/** @var CraftVariable $variable **/
				$variable = $event->sender;
				$variable->set('recurringOrders', RecurringOrders::$plugin);
			}
		);

	}

	/**
	 * Attaches custom behaviors to Order and OrderQuery components
	 */
	private function _attachComponentBehaviors() {

		Event::on(
			Order::class,
			Order::EVENT_INIT,
			function (Event $event) {
				/** @var Order $order **/
				$order = $event->sender;
				$order->attachBehavior('recurringOrder', RecurringOrderBehavior::class);
			}
		);

		Event::on(
			OrderQuery::class,
			OrderQuery::EVENT_INIT,
			function (Event $event) {
				/** @var OrderQuery $orderQuery **/
				$orderQuery = $event->sender;
				$orderQuery->attachBehavior('recurringOrderQuery', RecurringOrderQueryBehavior::class);
			}
		);

	}

	/**
	 * Registers template hooks to add Recurring Orders controls to key Craft Commerce CP pages.
	 */
	private function _registerTemplateHooks()
	{

		Craft::$app->view->hook('cp.commerce.order.edit.main-pane', function(array &$context) {
			return Craft::$app->view->renderTemplate('recurring-orders/_cp/_orderDetails', $context);
		});

	}

	/**
	 * Registers handlers for various Event hooks
	 */
	private function _registerEventHandlers()
	{

		/*
		 * Extra processing when Craft assembles its list of CP nav links.
		 */
		Event::on(
			Cp::class,
			Cp::EVENT_REGISTER_CP_NAV_ITEMS,
			function (RegisterCpNavItemsEvent $event) {
				$event->navItems = CpHelper::modifyCpNavItems($event->navItems);
			}
		);

		/*
		 * Extra processing before an Order element is saved
		 */
		Event::on(
			Elements::class,
			Elements::EVENT_BEFORE_SAVE_ELEMENT,
			function (ElementEvent $event) {
				$element = $event->element;
				if ($element instanceof Order)
				{
					$this->orders->beforeSaveOrder($element);
				}
			}
		);

		/*
		 * Extra processing after an Order element is saved
		 */
		Event::on(
			Elements::class,
			Elements::EVENT_AFTER_SAVE_ELEMENT,
			function (ElementEvent $event) {
				$element = $event->element;
				if ($element instanceof Order)
				{
					$this->orders->afterSaveOrder($element);
				}
			}
		);

		/*
		 * Register custom Sort Options for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_SORT_OPTIONS,
			[Orders::class, 'registerSortOptions']
		);

		/*
		 * Register custom Table Attributes for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_TABLE_ATTRIBUTES,
			[Orders::class, 'registerTableAttributes']
		);

		/*
		 * Tweak the Default Table Attributes for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_DEFAULT_TABLE_ATTRIBUTES,
			[Orders::class, 'registerDefaultTableAttributes']
		);

		/*
		 * Define custom HTML to represent attributes in Order index tables
		 */
		Event::on(
			Order::class,
			Order::EVENT_SET_TABLE_ATTRIBUTE_HTML,
			[Orders::class, 'setTableAttributeHtml']
		);

		/*
		 * Register custom Sources for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_SOURCES,
			[Orders::class, 'registerSources']
		);

	}

}
