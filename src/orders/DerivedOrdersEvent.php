<?php
namespace topshelfcraft\recurringorders\orders;

use craft\commerce\elements\Order;
use yii\base\Event;

class DerivedOrdersEvent extends Event
{

	/**
	 * @var Order The Originating Order, from which new Recurring Orders will potentially be derived.
	 */
	public $originatingOrder;

	/**
	 * @var Order[] The newly derived Orders, if there are any.
	 */
	public $derivedOrders = [];

}
