<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use craft\helpers\DateTimeHelper;
use topshelfcraft\recurringorders\base\BaseRecord;
use yii\base\Exception;

/**
 * A record of the status and recurrence information for a Recurring Order.
 *
 * @property int $id
 * @property string $status
 * @property string $errorReason
 * @property int $errorCount
 * @property string $recurrenceInterval
 * @property \DateTime $lastRecurrence
 * @property \DateTime $nextRecurrence
 * @property int $paymentSourceId
 */
class RecurringOrderRecord extends BaseRecord
{

	const STATUS_ACTIVE = 'active';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_ERROR = 'error';
	const STATUS_PAUSED = 'paused';
	const STATUS_UNSCHEDULED = 'unscheduled';

	const ERROR_DISCOUNT_UNAVAILABLE = 'productUnavailable';
	const ERROR_NO_PAYMENT_SOURCE = 'noPaymentSource';
	const ERROR_PAYMENT_ISSUE = 'paymentIssue';
	const ERROR_PAYMENT_SOURCE_EXPIRED = 'paymentSourceExpired';
	const ERROR_PRODUCT_UNAVAILABLE = 'discountUnavailable';

	const TableName = 'recurringorders_recurringorders';

	/**
	 * @var array
	 */
	protected $dateTimeAttributes = [
		'lastRecurrence',
		'nextRecurrence',
	];

	/**
	 * @param $name
	 * @param $value
	 *
	 * @throws \Exception if the `recurrenceInterval` is invalid.
	 */
	public function __set($name, $value)
	{
		if ($name === 'recurrenceInterval')
		{
			// TODO: Move this into validation and make it Yii-ish
			if (!OrdersHelper::isValidInterval($value))
			{
				throw new Exception("{$value} is not a valid interval.");
			}
		}
		parent::__set($name, $value);
	}

	/**
	 * @throws \Exception if `recurrenceInterval` is invalid.
	 */
	public function prepareForDb()
	{
		if ($this->recurrenceInterval instanceof \DateInterval)
		{
			$this->recurrenceInterval = OrdersHelper::durationInSeconds($this->recurrenceInterval);
		}
		parent::prepareForDb();
	}

	/**
	 * @return RecurringOrderQuery
	 */
	public static function find()
	{
		return new RecurringOrderQuery(static::class);
	}

	/**
	 * @return string
	 */
	public function getStatus()
	{
		/*
		 * A record may have an "Active" status, but its recurrence schedule isn't set yet,
		 * in which case it gets the special implicit status of "Unscheduled"
		 */
		if ($this->status === self::STATUS_ACTIVE)
		{
			if (empty($this->recurrenceInterval) || empty($this->nextRecurrence))
			{
				return self::STATUS_UNSCHEDULED;
			}
		}
		return $this->status;
	}

	/**
	 * Whether the Recurring Order is Active, AND has both a Recurrence Interval and Next Recurrence
	 *
	 * @return bool
	 */
	public function getIsScheduled()
	{
		return $this->getStatus() === self::STATUS_ACTIVE;
	}

	/**
	 * @return string|null
	 */
	public function getHumanReadableRecurrenceInterval()
	{
		if ($this->recurrenceInterval)
		{
			try
			{
				return DateTimeHelper::humanDurationFromInterval(
					OrdersHelper::normalizeInterval($this->recurrenceInterval)
				);
			}
			catch (\Exception $e)
			{
				Craft::error($e->getMessage());
			}
		}
		return null;
	}

	/**
	 * Saves the Recurring Order record. If it has changed since it was last retrieved/saved, a Recurring Order History
	 * record is saved as well.
	 *
	 * @param bool $runValidation
	 * @param null $attributeNames
	 *
	 * @return bool
	 */
	public function save($runValidation = true, $attributeNames = null)
	{
		/*
		 * We're doing this inside of save() rather than afterSave() because save() resets dirty attributes status.
		 */
		$isDirty = !empty($this->getDirtyAttributes());
		if ($saved = parent::save($runValidation, $attributeNames))
		{
			if ($isDirty)
			{
				$historyRecord = new RecurringOrderHistoryRecord([
					'orderId' => $this->id,
					'status' => $this->status,
					'errorReason' => $this->errorReason,
					'errorCount' => $this->errorCount,
					'recurrenceInterval' => $this->recurrenceInterval,
					'updatedByUserId' => Craft::$app->getUser()->id,
				]);
				// TODO: Wrap in Transaction
				$historyRecord->save();
			}
		}
		return $saved;
	}

}
