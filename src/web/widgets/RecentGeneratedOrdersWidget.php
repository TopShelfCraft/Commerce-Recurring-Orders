<?php
namespace topshelfcraft\recurringorders\web\widgets;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\commerce\widgets\Orders as OrdersWidget;
use craft\helpers\StringHelper;
use topshelfcraft\recurringorders\meta\RecurringOrderQuery;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;
use topshelfcraft\recurringorders\RecurringOrders;

/**
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 */
class RecentGeneratedOrdersWidget extends OrdersWidget
{

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--recent-generated-orders';

	/**
	 * @inheritdoc
	 */
	public static function isSelectable(): bool
	{
		return parent::isSelectable() && Craft::$app->getUser()->checkPermission('commerce-manageOrders');
	}

	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		return RecurringOrders::t('Recently Generated Orders');
	}

	/**
	 * @inheritdoc
	 */
	public static function icon(): string
	{
		return Craft::getAlias('@recurring-orders/icon-mask.svg');
	}

	/**
	 * @inheritdoc
	 */
	public function getTitle(): string
	{

		if ($orderStatusId = $this->orderStatusId) {
			$orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);

			if ($orderStatus) {
				return RecurringOrders::t('Recently Generated Orders') . ' – ' . Commerce::t($orderStatus->name);
			}
		}

		return parent::getTitle();

	}

	/**
	 * @inheritdoc
	 */
	public function getBodyHtml()
	{

		$orders = $this->_getOrders();

		$id = $this->handle . StringHelper::randomString();
		$namespaceId = Craft::$app->getView()->namespaceInputId($id);

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/recentGeneratedOrders/body', [
			'orders' => $orders,
			'showStatuses' => $this->orderStatusId == null,
			'id' => $id,
			'namespaceId' => $namespaceId,
		]);

	}

	/**
	 * Returns the recent entries, based on the widget settings and user permissions.
	 *
	 * @return Order[]
	 */
	private function _getOrders(): array
	{

		$orderStatusId = $this->orderStatusId;

		/** @var RecurringOrderQuery $query */
		$query = Order::find();

		$query->isCompleted(true);
		$query->dateOrdered(':notempty:');
		$query->limit($this->limit);
		$query->orderBy('dateOrdered DESC');

		if ($orderStatusId) {
			$query->orderStatusId($orderStatusId);
		}

		$query->hasParentOrder();
		return $query->all();

	}

}
