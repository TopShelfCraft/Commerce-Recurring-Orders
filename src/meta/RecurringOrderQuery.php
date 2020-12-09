<?php
namespace steadfast\recurringorders\meta;

use craft\commerce\elements\db\OrderQuery;
use steadfast\recurringorders\orders\RecurringOrderQueryBehavior;

/**
 * @mixin RecurringOrderQueryBehavior
 */
abstract class RecurringOrderQuery extends OrderQuery
{

}
