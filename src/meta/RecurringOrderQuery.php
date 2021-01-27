<?php
namespace beSteadfast\RecurringOrders\meta;

use craft\commerce\elements\db\OrderQuery;
use beSteadfast\RecurringOrders\orders\RecurringOrderQueryBehavior;

/**
 * @mixin RecurringOrderQueryBehavior
 */
abstract class RecurringOrderQuery extends OrderQuery
{

}
