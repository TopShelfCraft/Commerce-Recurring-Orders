<?php
namespace steadfast\recurringorders\orders;

use steadfast\recurringorders\base\BaseRecord;

/**
 * A snapshot of the status and recurrence interval for a Recurring Order when it was updated.
 *
 * @property int $id
 * @property int $orderId
 * @property string $status
 * @property string $errorReason
 * @property int $errorCount
 * @property string $recurrenceInterval
 * @property int $updatedByUserId
 */
class RecurringOrderHistoryRecord extends BaseRecord
{

	const TableName = 'recurringorders_recurringorderhistories';

}
