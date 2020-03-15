<?php
namespace topshelfcraft\recurringorders\orders;

use topshelfcraft\recurringorders\base\BaseRecord;

/**
 * A record that ties an Order to the parent Recurring Order from which it was generated.
 *
 * @property int $id
 * @property int $parentOrderId
 */
class GeneratedOrderRecord extends BaseRecord
{

	const TableName = 'recurringorders_generatedorders';

}
