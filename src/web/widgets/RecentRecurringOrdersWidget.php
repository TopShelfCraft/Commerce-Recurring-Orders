<?php
namespace TopShelfCraft\RecurringOrders\web\widgets;

use TopShelfCraft\RecurringOrders\meta\RecurringOrderQuery;
use TopShelfCraft\RecurringOrders\orders\RecurringOrderRecord;
use TopShelfCraft\RecurringOrders\RecurringOrders;
use TopShelfCraft\RecurringOrders\web\assets\OrdersWidgetAsset;
use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\helpers\StringHelper;

class RecentRecurringOrdersWidget extends Widget
{

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--recent-recurring-orders';

    /**
     * @var string|null
     */
    public $recurrenceStatus;

    /**
     * @var int
     */
    public $limit = 10;

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
    	// TODO: Translate
        if ($this->recurrenceStatus) {
			return RecurringOrders::t( 'Recent Recurring Orders') . ' - ' . RecurringOrders::t('_status:'.$this->recurrenceStatus);
        }
        return static::displayName();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {

        $orders = $this->_getOrders();

        $id = $this->handle . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/recentRecurringOrders/body', [
            'orders' => $orders,
            'showStatuses' => $this->recurrenceStatus === null,
            'id' => $id,
            'namespaceId' => $namespaceId,
        ]);

    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {

        Craft::$app->getView()->registerAssetBundle(OrdersWidgetAsset::class);

        $id = $this->handle . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerJs("new Craft.RecurringOrders.OrdersWidgetSettings('" . $namespaceId . "');");

        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/recentRecurringOrders/settings', [
            'id' => $id,
            'widget' => $this,
            'statuses' => RecurringOrders::getInstance()->orders->getAllRecurrenceStatuses(),
        ]);

    }

    /**
     * Returns the recent entries, based on the widget settings and user permissions.
     *
     * @return Order[]
     */
    private function _getOrders(): array
    {

        /** @var RecurringOrderQuery $query */
        $query = Order::find();

        $query->isCompleted(true);
        $query->dateOrdered(':notempty:');
        $query->hasRecurrenceStatus(true);
        $query->limit($this->limit);
        $query->orderBy('dateOrdered DESC');

        if ($this->recurrenceStatus)
        {
        	// TODO: This is... fragile?
            if ($this->recurrenceStatus === RecurringOrderRecord::STATUS_UNSCHEDULED)
			{
				$query->recurrenceStatus(RecurringOrderRecord::STATUS_ACTIVE);
				$query->hasRecurrenceSchedule(false);
			}
            else
			{
				$query->recurrenceStatus($this->recurrenceStatus);
				$query->hasRecurrenceSchedule(true);
			}
        }

        return $query->all();

    }

    /*
     * Static
     */

	/**
	 * @inheritdoc
	 */
	public static function displayName(): string
	{
		// TODO: Translate
		return RecurringOrders::t( 'Recent Recurring Orders');
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
	public static function isSelectable(): bool
	{
		return parent::isSelectable() && Craft::$app->getUser()->checkPermission('commerce-manageOrders');
	}

}
