<?php
namespace topshelfcraft\recurringorders\orders;

use craft\commerce\elements\db\OrderQuery;
use craft\events\CancelableEvent;
use craft\events\PopulateElementEvent;
use yii\base\Behavior;

class RecurringOrderQueryBehavior extends Behavior
{

	/**
	 * @inheritdoc
	 */
	public function init()
	{
		parent::init();
	}

	/**
	 * @inheritdoc
	 */
	public function events()
	{
		return [
			OrderQuery::EVENT_AFTER_PREPARE => 'afterPrepare',
			OrderQuery::EVENT_AFTER_POPULATE_ELEMENT => 'afterPopulateElement'
		];
	}

	/**
	 * @param CancelableEvent $event
	 */
	public function afterPrepare(CancelableEvent $event)
	{

	}

	/**
	 * @param PopulateElementEvent $event
	 */
	public function afterPopulateElement(PopulateElementEvent $event)
	{

	}

}
