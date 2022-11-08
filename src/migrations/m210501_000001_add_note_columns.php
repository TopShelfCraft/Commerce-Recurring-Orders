<?php
namespace TopShelfCraft\RecurringOrders\migrations;

use TopShelfCraft\RecurringOrders\orders\RecurringOrderHistoryRecord;
use TopShelfCraft\RecurringOrders\orders\RecurringOrderRecord;
use craft\db\Migration;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m210501_000001_add_note_columns extends Migration
{

	/**
	 * @inheritDoc
	 */
	public function safeUp(): bool
	{
		$this->addColumn(RecurringOrderRecord::tableName(), 'note', $this->text()->after('paymentSourceId'));
		$this->addColumn(RecurringOrderHistoryRecord::tableName(), 'note', $this->text()->after('errorCount'));
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropColumn(RecurringOrderRecord::tableName(), 'note');
		$this->dropColumn(RecurringOrderHistoryRecord::tableName(), 'note');
		return true;
	}
}
