<?php
namespace TopShelfCraft\RecurringOrders\orders;

use TopShelfCraft\RecurringOrders\base\BaseRecord;

/**
 * A snapshot of the status and recurrence interval for a Recurring Order when it was updated.
 *
 * @property int $id
 * @property int $orderId
 * @property string $status
 * @property string $errorReason
 * @property int $errorCount
 * @property string|null $note
 * @property string $recurrenceInterval
 * @property int $updatedByUserId
 * @property string $updatedAttributes
 */
class RecurringOrderHistoryRecord extends BaseRecord
{

	const TableName = 'recurringorders_recurringorderhistories';

}
