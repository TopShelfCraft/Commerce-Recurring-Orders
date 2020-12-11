<?php
namespace steadfast\recurringorders;

use Craft;
use craft\base\Plugin;
use craft\commerce\elements\db\OrderQuery;
use craft\commerce\elements\Order;
use craft\console\Application as ConsoleApplication;
use craft\helpers\FileHelper;
use craft\services\Dashboard;
use craft\web\Application as WebApplication;
use craft\web\twig\variables\Cp;
use craft\web\twig\variables\CraftVariable;
use michaelrog\paymentsourcestools\PaymentSourcesTools;
use steadfast\recurringorders\config\Settings;
use steadfast\recurringorders\orders\Orders;
use steadfast\recurringorders\orders\RecurringOrderBehavior;
use steadfast\recurringorders\orders\RecurringOrderQueryBehavior;
use steadfast\recurringorders\web\cp\CpCustomizations;
use yii\base\Event;

/**
 * Module to encapsulate Recurring Orders functionality.
 *
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('recurring-orders')`
 *
 * @see http://www.yiiframework.com/doc-2.0/guide-structure-modules.html
 *
 * @property CpCustomizations $cpCustomizations
 * @property Orders $orders
 *
 * @method Settings getSettings()
 *
 * @todo: Eager load Recurring Order records
 * @todo: Recurrence Count, Recurrence Limit
 * @todo: Expiration dates?
 */
class RecurringOrders extends Plugin
{

	/**
	 * @var bool
	 */
	public $hasCpSettings = false;

	/**
	 * @var bool
	 */
	public $hasCpSection = false;

	/**
	 * @var string
	 */
	public $schemaVersion = '0.0.31.0';


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
			'cpCustomizations' => CpCustomizations::class,
			'orders' => Orders::class,
		];

		parent::__construct($id, $parent, $config);

	}

	/**
	 * Initializes the module.
	 */
	public function init()
	{

		Craft::setAlias('@recurring-orders', __DIR__);
		parent::init();

		$this->_attachComponentBehaviors();
		$this->_registerEventHandlers();
		$this->_attachVariableGlobal();
		$this->_registerTemplateHooks();
		$this->_initPaymentSourceTools();

		// Register controllers via namespace map

		if (Craft::$app instanceof ConsoleApplication)
		{
			$this->controllerNamespace = 'steadfast\\recurringorders\\controllers\\console';
		}
		if (Craft::$app instanceof WebApplication)
		{
			$this->controllerNamespace = 'steadfast\\recurringorders\\controllers\\web';
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
				$variable->set('recurringOrders', $this);
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

		Craft::$app->view->hook('cp.layouts.base', [CpCustomizations::class, 'cpLayoutsBaseHook']);
		Craft::$app->view->hook('cp.commerce.order.edit', [CpCustomizations::class, 'cpCommerceOrderEditHook']);
		Craft::$app->view->hook('cp.commerce.order.edit.main-pane', [CpCustomizations::class, 'cpCommerceOrderEditMainPaneHook']);

		if ($this->getSettings()->showUserRecurringOrdersTab) {
			Craft::$app->getView()->hook('cp.users.edit', [CpCustomizations::class, 'cpUsersEditHook']);
			Craft::$app->getView()->hook('cp.users.edit.content', [CpCustomizations::class, 'cpUsersEditContentHook']);
		}

	}

	/**
	 * Registers handlers for various Event hooks
	 */
	private function _registerEventHandlers()
	{

		/*
		 * Extra processing before an Order element is saved
		 */
		Event::on(
			Order::class,
			Order::EVENT_BEFORE_SAVE,
			[$this->orders, 'handleOrderBeforeSave']
		);

		/*
		 * Extra processing after an Order element is saved
		 */
		Event::on(
			Order::class,
			Order::EVENT_AFTER_SAVE,
			[$this->orders, 'handleOrderAfterSave']
		);

		/*
		 * Extra processing after an Order is Completed
		 */
		Event::on(
			Order::class,
			Order::EVENT_AFTER_COMPLETE_ORDER,
			[$this->orders, 'handleAfterCompleteOrder']
		);

		/*
		 * Extra processing when Craft assembles its list of CP nav links.
		 */
		Event::on(
			Cp::class,
			Cp::EVENT_REGISTER_CP_NAV_ITEMS,
			[$this->cpCustomizations, 'handleModifyCpNavItems']
		);

		/*
		 * Register custom Sort Options for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_SORT_OPTIONS,
			[$this->cpCustomizations, 'handleRegisterSortOptions']
		);

		/*
		 * Register custom Table Attributes for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_TABLE_ATTRIBUTES,
			[$this->cpCustomizations, 'handleRegisterTableAttributes']
		);

		/*
		 * Tweak the Default Table Attributes for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_DEFAULT_TABLE_ATTRIBUTES,
			[$this->cpCustomizations, 'handleRegisterDefaultTableAttributes']
		);

		/*
		 * Define custom HTML to represent attributes in Order index tables
		 */
		Event::on(
			Order::class,
			Order::EVENT_SET_TABLE_ATTRIBUTE_HTML,
			[$this->cpCustomizations, 'handleSetTableAttributeHtml']
		);

		/*
		 * Register custom Sources for Order elements
		 */
		Event::on(
			Order::class,
			Order::EVENT_REGISTER_SOURCES,
			[$this->cpCustomizations, 'handleRegisterSources']
		);

		Event::on(
			Dashboard::class,
			Dashboard::EVENT_REGISTER_WIDGET_TYPES,
			[$this->cpCustomizations, 'handleRegisterWidgetTypes']
		);

	}

	/**
	 * Import/initialize extra Payment Sources functionality
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	private function _initPaymentSourceTools()
	{
		PaymentSourcesTools::registerModule();
	}

}
