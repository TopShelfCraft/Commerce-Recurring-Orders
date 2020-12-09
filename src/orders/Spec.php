<?php
namespace steadfast\recurringorders\orders;

use craft\base\Model;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\Json;

class Spec extends Model
{

	/**
	 * @var string
	 */
	protected $status;

	/**
	 * @var string
	 */
	protected $recurrenceInterval;

	/**
	 * @var \DateTime
	 */
	protected $nextRecurrence;

	/**
	 * @var int
	 */
	protected $paymentSourceId;

	/**
	 * @param array|string $config
	 */
	public function __construct($config = [])
	{
		if (is_string($config) && ($data = json_decode($config, true)) && is_array($data))
		{
			$config = $data;
		}
		parent::__construct($config);
	}

	/**
	 * @return string|null
	 */
	public function getJsonForDb()
	{
		$attributes = [
			'status' => $this->status,
			'recurrenceInterval' => $this->recurrenceInterval,
			'nextRecurrence' => $this->nextRecurrence ? Db::prepareDateForDb($this->nextRecurrence) : null,
			'paymentSourceId' => $this->paymentSourceId,
		];
		if (empty(array_filter($attributes)))
		{
			return null;
		}
		return Json::encode($attributes);
	}

	/**
	 * @return bool
	 */
	public function isEmpty()
	{
		return empty($this->getJsonForDb());
	}

	/**
	 * @return string|null
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * @param $value
	 */
	public function setStatus($value)
	{
		// TODO: Validate?
		$this->status = ($value ?: null);
	}

	/**
	 * @return string|int|null
	 */
	public function getRecurrenceInterval()
	{
		return $this->recurrenceInterval;
	}

	/**
	 * @param $value
	 */
	public function setRecurrenceInterval($value)
	{
		// TODO: Validate?
		$this->recurrenceInterval = ($value ?: null);
	}

	/**
	 * @return \DateTime|null
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime
	 */
	public function getNextRecurrence()
	{
		return ($this->nextRecurrence ? DateTimeHelper::toDateTime($this->getNextRecurrence()) : null);
	}

	/**
	 * @param $value
	 *
	 * @throws \Exception if DateTimeHelper cannot convert the value to a DateTime
	 */
	public function setNextRecurrence($value)
	{
		$value = DateTimeHelper::toDateTime($value);
		$this->nextRecurrence = ($value ?: null);
	}

	/**
	 * @return int|null
	 */
	public function getRecurrencePaymentSourceId()
	{
		return $this->paymentSourceId;
	}

	/**
	 * @param $value
	 */
	public function setRecurrencePaymentSourceId($value)
	{
		$this->paymentSourceId = ((int)$value ?: null);
	}

}
