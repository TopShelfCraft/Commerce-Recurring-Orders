<?php
namespace TopShelfCraft\RecurringOrders\migrations;

use craft\db\Migration;
use TopShelfCraft\RecurringOrders\orders\RecurringOrderRecord;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m200704_000001_add_retryDate_column extends Migration
{

	/**
	 * @inheritDoc
	 */
	public function safeUp(): bool
	{
		$this->addColumn(RecurringOrderRecord::tableName(), 'retryDate', $this->dateTime()->after('errorCount'));
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropColumn(RecurringOrderRecord::tableName(), 'retryDate');
		return true;
	}
}
