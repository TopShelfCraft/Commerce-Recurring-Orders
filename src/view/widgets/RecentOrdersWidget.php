<?php
namespace topshelfcraft\recurringorders\view\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\commerce\web\assets\orderswidget\OrdersWidgetAsset;
use craft\helpers\StringHelper;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;

/**
 * Class Orders
 *
 * @property string|false $bodyHtml the widget's body HTML
 * @property string $settingsHtml the component’s settings HTML
 * @property string $title the widget’s title
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class RecentOrdersWidget extends Widget
{
    /**
     * @var int|null
     */
    public $orderStatusId;

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
    	// TODO: Translate.
        return Commerce::t( 'Recent Recurring Orders');
    }

    /**
     * @inheritdoc
     */
    public static function icon(): string
    {
        return Craft::getAlias('@craft/commerce/icon-mask.svg');
    }

    /**
     * @inheritdoc
     */
    public function getTitle(): string
    {
        if ($orderStatusId = $this->orderStatusId) {
            $orderStatus = Commerce::getInstance()->getOrderStatuses()->getOrderStatusById($orderStatusId);

            if ($orderStatus) {
                return Commerce::t( 'Recent Orders') . ' – ' . Commerce::t( $orderStatus->name);
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

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);


        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/orders/recent/body', [
            'orders' => $orders,
            'showStatuses' => $this->orderStatusId === null,
            'id' => $id,
            'namespaceId' => $namespaceId,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): string
    {
        $orderStatuses = Commerce::getInstance()->getOrderStatuses()->getAllOrderStatuses();

        Craft::$app->getView()->registerAssetBundle(OrdersWidgetAsset::class);

        $id = 'recent-orders-settings-' . StringHelper::randomString();
        $namespaceId = Craft::$app->getView()->namespaceInputId($id);

        Craft::$app->getView()->registerJs("new Craft.Commerce.OrdersWidgetSettings('" . $namespaceId . "');");

        return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/orders/recent/settings', [
            'id' => $id,
            'widget' => $this,
            'orderStatuses' => $orderStatuses,
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
        $limit = $this->limit;

        $query = Order::find();
        /** @var RecurringOrderQueryBehavior $query */
        $query->isCompleted(true);
        $query->dateOrdered(':notempty:');
        $query->hasRecurrenceSchedule(true);
        $query->limit($limit);
        $query->orderBy('dateOrdered DESC');

        if ($orderStatusId) {
            $query->orderStatusId($orderStatusId);
        }

        return $query->all();
    }
}
