<?php
namespace steadfast\recurringorders\orders;

use yii\base\Event;

class RecurrenceStatusChangeEvent extends Event
{

	/**
	 * @var string The original Recurrence Status, prior to the present change.
	 */
	public $oldStatus;

}
