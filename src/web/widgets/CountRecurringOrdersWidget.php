<?php
namespace TopShelfCraft\RecurringOrders\web\widgets;

use TopShelfCraft\RecurringOrders\meta\RecurringOrderQuery;
use TopShelfCraft\RecurringOrders\orders\RecurringOrderRecord;
use TopShelfCraft\RecurringOrders\RecurringOrders;
use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\StringHelper;

class CountRecurringOrdersWidget extends Widget
{

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--count-recurring-orders';

	/**
	 * @var string|null
	 */
	public $recurrenceStatus;

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string
	{
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function getSubtitle()
	{
		return '';
	}

	/**
	 * @inheritdoc
	 */
	public function getBodyHtml()
	{

		/** @var RecurringOrderQuery $query */
		$query = Order::find()->isCompleted();
		$query->hasRecurrenceStatus(true);
		if ($this->recurrenceStatus)
		{
			if ($this->recurrenceStatus === RecurringOrderRecord::STATUS_UNSCHEDULED)
			{
				$query->recurrenceStatus(RecurringOrderRecord::STATUS_ACTIVE);
				$query->hasRecurrenceSchedule(false);
			}
			else
			{
				$query->hasRecurrenceSchedule(true);
				$query->recurrenceStatus($this->recurrenceStatus);
			}
		}

		$number = $query->count();

		// TODO: Translate
		$descriptor =  RecurringOrders::t($number == 1 ? 'Recurring Order' : 'Recurring Orders');

		$timeFrame = $this->recurrenceStatus ? RecurringOrders::t('_status:' . $this->recurrenceStatus) : null;

		$id = $this->handle . StringHelper::randomString();
		$namespaceId = Craft::$app->getView()->namespaceInputId($id);

		$view = Craft::$app->getView();
		$view->registerAssetBundle(StatWidgetsAsset::class);

		return $view->renderTemplate(
			'recurring-orders/cp/widgets/_numberWidgetBody',
			compact(
				'namespaceId',
				'number',
				'descriptor',
				'timeFrame'
			)
		);

	}

	/**
	 * @inheritdoc
	 */
	public function getSettingsHtml(): string
	{

		$id = $this->handle . StringHelper::randomString();
		$namespaceId = Craft::$app->getView()->namespaceInputId($id);

		Craft::$app->getView()->registerJs("new Craft.RecurringOrders.OrdersWidgetSettings('" . $namespaceId . "');");

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/countAllRecurringOrders/settings', [
			'id' => $id,
			'namespaceId' => $namespaceId,
			'widget' => $this,
			'statuses' => RecurringOrders::getInstance()->orders->getAllRecurrenceStatuses(),
		]);

	}

	/*
	 * Static
	 */

	/**
	 * @inheritDoc
	 */
	public static function displayName(): string
	{
		// TODO: Translate.
		return RecurringOrders::t('Count Recurring Orders');
	}

	/**
	 * @inheritDoc
	 */
	public static function icon(): string
	{
		return Craft::getAlias('@recurring-orders/icon-mask.svg');
	}

	/**
	 * @inheritDoc
	 */
	public static function isSelectable(): bool
	{
		return parent::isSelectable() && Craft::$app->getUser()->checkPermission('commerce-manageOrders');
	}

	/**
	 * @inheritDoc
	 */
	public static function maxColspan()
	{
		return 1;
	}

}
