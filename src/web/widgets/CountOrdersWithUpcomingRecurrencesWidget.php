<?php
namespace beSteadfast\RecurringOrders\web\widgets;

use beSteadfast\RecurringOrders\meta\RecurringOrderQuery;
use beSteadfast\RecurringOrders\misc\TimeHelper;
use beSteadfast\RecurringOrders\RecurringOrders;
use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;

class CountOrdersWithUpcomingRecurrencesWidget extends Widget
{

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--count-orders-with-upcoming-recurrences';

	/**
	 * @var int
	 */
	public $dateRangeQty;

	/**
	 * @var string
	 */
	public $dateRangeUnit;

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

		$dateRangeInterval = 'P' . $this->dateRangeQty . $this->dateRangeUnit;
		$interval = TimeHelper::normalizeInterval($dateRangeInterval);
		$humanDuration = $interval ? DateTimeHelper::humanDurationFromInterval($interval) : null;

		$nextRecurrenceThreshold = TimeHelper::fromNow($interval)->getTimestamp();

		/** @var RecurringOrderQuery $query */
		$query = Order::find()->isCompleted();
		$query->hasRecurrenceSchedule();
		$query->nextRecurrence('<'.$nextRecurrenceThreshold);

		$number = $query->count();

		// TODO: Translate
		$descriptor = "Upcoming Order " . ($number == 1 ? 'Recurrence' : 'Recurrences');

		// TODO: Translate
		$timeFrame = RecurringOrders::t('Next {interval}', ['interval' => $humanDuration]);

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

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/countOrdersWithUpcomingRecurrences/settings', [
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
		return RecurringOrders::t( 'Count Orders with Upcoming Recurrences');
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
