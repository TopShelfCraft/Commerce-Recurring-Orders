<?php
namespace TopShelfCraft\RecurringOrders\meta;

use craft\commerce\elements\db\OrderQuery;
use TopShelfCraft\RecurringOrders\orders\RecurringOrderQueryBehavior;

/**
 * @mixin RecurringOrderQueryBehavior
 */
abstract class RecurringOrderQuery extends OrderQuery
{

}
