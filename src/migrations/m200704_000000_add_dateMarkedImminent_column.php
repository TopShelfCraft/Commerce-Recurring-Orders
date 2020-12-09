<?php
namespace steadfast\recurringorders\migrations;

use craft\db\Migration;
use steadfast\recurringorders\orders\RecurringOrderRecord;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m200704_000000_add_dateMarkedImminent_column extends Migration
{

	/**
	 * @inheritDoc
	 */
	public function safeUp(): bool
	{
		$this->addColumn(RecurringOrderRecord::tableName(), 'dateMarkedImminent', $this->dateTime()->after('nextRecurrence'));
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropColumn(RecurringOrderRecord::tableName(), 'dateMarkedImminent');
		return true;
	}
}
