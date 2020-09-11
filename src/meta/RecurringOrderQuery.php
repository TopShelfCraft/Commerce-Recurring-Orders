<?php
namespace topshelfcraft\recurringorders\meta;

use craft\commerce\elements\db\OrderQuery;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;

/**
 * @mixin RecurringOrderQueryBehavior
 */
abstract class RecurringOrderQuery extends OrderQuery
{

}
