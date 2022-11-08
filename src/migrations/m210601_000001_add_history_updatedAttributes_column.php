<?php
namespace TopShelfCraft\RecurringOrders\migrations;

use TopShelfCraft\RecurringOrders\orders\RecurringOrderHistoryRecord;
use craft\db\Migration;

/**
 * The class name is the UTC timestamp in the format of mYYMMDD_HHMMSS_migrationName
 */
class m210601_000001_add_history_updatedAttributes_column extends Migration
{

	/**
	 * @inheritDoc
	 */
	public function safeUp(): bool
	{
		$this->addColumn(RecurringOrderHistoryRecord::tableName(), 'updatedAttributes', $this->text()->after('updatedByUserId'));
		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		$this->dropColumn(RecurringOrderHistoryRecord::tableName(), 'updatedAttributes');
		return true;
	}

}
