<?php
namespace beSteadfast\RecurringOrders\meta;

use craft\commerce\elements\Order;
use beSteadfast\RecurringOrders\orders\RecurringOrderBehavior;

/**
 * @mixin RecurringOrderBehavior
 */
abstract class RecurringOrder extends Order
{

}
