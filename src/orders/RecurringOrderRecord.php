<?php
namespace topshelfcraft\recurringorders\orders;

use Craft;
use topshelfcraft\recurringorders\base\BaseRecord;
use topshelfcraft\recurringorders\misc\TimeHelper;
use yii\base\Exception;

/**
 * A record of the status and recurrence information for a Recurring Order.
 *
 * @property int $id
 * @property string $status
 * @property string $recurrenceInterval
 * @property \DateTime $lastRecurrence
 * @property \DateTime $nextRecurrence
 * @property \DateTime $dateMarkedImminent
 * @property int $paymentSourceId
 * @property mixed $spec
 * @property mixed $originatingOrderId
 * @property mixed $parentOrderId
 * @property string $errorReason
 * @property int $errorCount
 * @property \DateTime $retryDate
 */
class RecurringOrderRecord extends BaseRecord
{

	const STATUS_ACTIVE = 'active';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_ERROR = 'error';
	const STATUS_PAUSED = 'paused';
	const STATUS_UNSCHEDULED = 'unscheduled';

	const ERROR_DISCOUNT_UNAVAILABLE = 'discountUnavailable';
	const ERROR_NO_PAYMENT_SOURCE = 'noPaymentSource';
	const ERROR_PAYMENT_ISSUE = 'paymentIssue';
	const ERROR_PAYMENT_SOURCE_EXPIRED = 'paymentSourceExpired';
	const ERROR_PRODUCT_UNAVAILABLE = 'productUnavailable';
	const ERROR_UNKNOWN = 'unknown';

	const TableName = 'recurringorders_recurringorders';

	/**
	 * @var array
	 */
	protected $dateTimeAttributes = [
		'lastRecurrence',
		'nextRecurrence',
		'dateMarkedImminent',
		'retryDate',
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
			if ($value instanceof \DateInterval)
			{
				$value = TimeHelper::durationInSeconds($this->recurrenceInterval);
			}
			// TODO: Move this into validation and make it Yii-ish
			if ($value && !TimeHelper::isValidInterval($value))
			{
				// TODO: Translate.
				throw new Exception("{$value} is not a valid interval.");
			}
		}
		if ($name === 'spec' && $value instanceof Spec)
		{
			$value = $value->getJsonForDb();
		}
		parent::__set($name, $value);
	}

	/**
	 *
	 */
	public function prepareForDb()
	{
		/*
		 * Depending on the record schema, this method may be "too late" to handle typecasting for db.
		 * Craft's ActiveRecord calls its own `_prepareValue` method as part of `__set`
		 * so, for example, if we sent an Object argument into this Record's setter, it may have already been stringified.
		 */
		parent::prepareForDb();
	}

	/**
	 * Saves the Recurring Order record. If it has changed since it was last retrieved/saved, a Recurring Order History
	 * record is saved as well.
	 *
	 * @param bool $runValidation
	 * @param null $attributeNames
	 *
	 * @return bool
	 *
	 * @throws \yii\db\Exception if there's a problem with the db Transaction.
	 */
	public function save($runValidation = true, $attributeNames = null)
	{

		// TODO: Wrap in transaction?

		/*
		 * We're doing this inside of save() rather than afterSave() because save() resets dirty attributes status.
		 */
		$isDirty = !empty($this->getDirtyAttributes([
			'orderId',
			'status',
			'errorReason',
			'errorCount',
			'recurrenceInterval',
		]));
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
				$saved = $saved && $historyRecord->save();
			}
		}

		return $saved;

	}

}
