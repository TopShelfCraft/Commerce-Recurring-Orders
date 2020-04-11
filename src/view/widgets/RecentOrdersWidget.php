<?php
namespace topshelfcraft\recurringorders\view\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\helpers\StringHelper;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;
use topshelfcraft\recurringorders\RecurringOrders;
use topshelfcraft\recurringorders\web\assets\OrdersWidgetAsset;

class RecentOrdersWidget extends Widget
{

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
    public static function isSelectable(): bool
    {
        return Craft::$app->getUser()->checkPermission('commerce-manageOrders');
    }

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return RecurringOrders::t( 'Recently Created Recurring Orders');
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
        if ($this->recurrenceStatus) {
			return RecurringOrders::t( 'Recently Created Recurring Orders') . ' - ' . RecurringOrders::t('_status:'.$this->recurrenceStatus);
        }
        return static::displayName();
    }

    /**
     * @inheritdoc
     */
    public function getBodyHtml()
    {

        $orders = $this->_getOrders();

        $id = 'recent-orders-settings-' . StringHelper::randomString();

        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/orders/recent/body', [
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

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerJs("new Craft.RecurringOrders.OrdersWidgetSettings('" . $namespaceId . "');");

        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/orders/recent/settings', [
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

        $query = Order::find();
        /** @var RecurringOrderQueryBehavior $query */

        $query->isCompleted(true);
        $query->dateOrdered(':notempty:');
        $query->hasRecurrenceSchedule(true);
        $query->limit($this->limit);
        $query->orderBy('dateOrdered DESC');

        if ($this->recurrenceStatus) {
            $query->recurrenceStatus($this->recurrenceStatus);
        }

        return $query->all();

    }

}
