<?php
namespace steadfast\recurringorders\web\widgets;

use Craft;
use craft\base\Widget;
use craft\commerce\elements\Order;
use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\helpers\DateTimeHelper;
use craft\helpers\StringHelper;
use steadfast\recurringorders\meta\RecurringOrderQuery;
use steadfast\recurringorders\misc\TimeHelper;
use steadfast\recurringorders\RecurringOrders;

class CountUpcomingRecurrencesWidget extends Widget
{

	/**
	 * @var string
	 */
	protected $handle = 'recurring-orders--count-upcoming-recurrences';

	/**
	 * @var string|null
	 */
	public $dateRangeInterval;

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
	public static function displayName(): string
	{
		// TODO: Translate
		return RecurringOrders::t( 'Count Upcoming Order Recurrences');
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

		$interval = $this->dateRangeInterval ? TimeHelper::normalizeInterval($this->dateRangeInterval) : null;
		$humanDuration = $interval ? DateTimeHelper::humanDurationFromInterval($interval) : null;

		$nextRecurrenceThreshold = $interval
			? TimeHelper::fromNow($interval)->getTimestamp()
			: strtotime('tomorrow');

		/** @var RecurringOrderQuery $query */
		$query = Order::find()->isCompleted();
		$query->hasRecurrenceSchedule();
		$query->nextRecurrence('<'.$nextRecurrenceThreshold);

		$number = $query->count();

		$descriptor = "Upcoming Order " . ($number == 1 ? 'Recurrence' : 'Recurrences');

		$timeFrame = $this->dateRangeInterval
			? RecurringOrders::t('Next {interval}', ['interval' => $humanDuration])
			: Craft::t('app', 'Today');

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
	 * @inheritDoc
	 */
	public static function maxColspan()
	{
		return 1;
	}

	/**
	 * @inheritdoc
	 */
	public function getSettingsHtml(): string
	{

		$id = 'total-orders' . StringHelper::randomString();
		$namespaceId = Craft::$app->getView()->namespaceInputId($id);

		return Craft::$app->getView()->renderTemplate('recurring-orders/cp/widgets/countUpcomingRecurrences/settings', [
			'id' => $id,
			'namespaceId' => $namespaceId,
			'widget' => $this,
		]);

	}

	/**
	 * @inheritdoc
	 */
	protected function defineRules(): array
	{

		$rules = parent::defineRules();

		$rules[] = [
			'dateRangeInterval',
			function ($attribute, $params, $validator) {
				if ($this->$attribute && !TimeHelper::isValidInterval($this->$attribute))
				{
					$this->addError($attribute, 'The Date Range Interval is not valid.');
				}
			}
		];

		return $rules;

	}

}
