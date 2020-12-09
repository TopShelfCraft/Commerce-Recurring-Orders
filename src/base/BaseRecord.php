<?php
namespace steadfast\recurringorders\base;

use craft\db\ActiveRecord;
use craft\helpers\DateTimeHelper;

abstract class BaseRecord extends ActiveRecord
{

	const TableName = '';

	protected $dateTimeAttributes = [];

	/**
	 * @inheritdoc
	 *
	 * @return string
	 */
	public static function tableName()
	{
		return '{{%'.static::TableName.'}}';
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	public function __get($name)
	{
		if (in_array($name, $this->dateTimeAttributes, true))
		{
			if (($value = parent::__get($name)) !== null)
			{
				return DateTimeHelper::toDateTime($value);
			}
		}
		return parent::__get($name);
	}

}
