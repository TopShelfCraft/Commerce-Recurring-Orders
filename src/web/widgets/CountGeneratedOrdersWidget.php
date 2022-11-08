<?php
namespace TopShelfCraft\RecurringOrders\web\widgets;

use TopShelfCraft\RecurringOrders\meta\RecurringOrderQuery;
use TopShelfCraft\RecurringOrders\misc\TimeHelper;
use TopShelfCraft\RecurringOrders\RecurringOrders;
use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\StringHelper;

class CountGeneratedOrdersWidget extends Widget
{

	use DateRangeWidgetTrait;

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--count-generated-orders';

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

		if ($this->dateRange && $this->dateRange !== TimeHelper::DATE_RANGE_CUSTOM)
		{

			$weekStartDay = 1;

			if ($user = Craft::$app->getUser()->getIdentity())
			{
				$weekStartDay = $user->getPreference('weekStartDay');
			}

			$this->setDatesFromRange($this->dateRange, $weekStartDay);

		}

		/** @var RecurringOrderQuery $query */
		$query = Order::find()->isCompleted();
		$query->hasParentOrder();

		$query->dateOrdered([
			'and',
			$this->startDate ? '>='.$this->startDate : 'not null',
			$this->endDate ? '<'.$this->endDate : 'not null',
		]);

		$number = $query->count();

		// TODO: Translate
		$descriptor = "Generated " . ($number == 1 ? 'Order' : 'Orders');

		// TODO: Translate
		$timeFrame = TimeHelper::getDateRangeWording($this->dateRange, $this->startDate, $this->endDate);

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

		$id = 'total-orders' . StringHelper::randomString();
		$namespaceId = Craft::$app->getView()->namespaceInputId($id);

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/countGeneratedOrders/settings', [
			'id' => $id,
			'namespaceId' => $namespaceId,
			'widget' => $this,
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
		// TODO: Translate
		return RecurringOrders::t( 'Count Generated Orders');
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
