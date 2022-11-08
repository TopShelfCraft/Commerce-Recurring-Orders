<?php
namespace TopShelfCraft\RecurringOrders\orders;

use craft\commerce\elements\Order;
use yii\base\Event;

/**
 * @property Order $sender
 */
class RecurrenceStatusChangeEvent extends Event
{

	/**
	 * @var string The original Recurrence Status, prior to the present change.
	 */
	public $oldStatus;

}
