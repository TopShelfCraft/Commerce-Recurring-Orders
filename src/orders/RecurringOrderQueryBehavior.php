<?php
namespace topshelfcraft\recurringorders\orders;

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
		return [];
	}

}
