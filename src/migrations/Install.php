<?php
namespace topshelfcraft\recurringorders\migrations;

use craft\commerce\records\Order;
use craft\commerce\records\PaymentSource;
use craft\db\Migration;
use craft\records\User;
use topshelfcraft\recurringorders\orders\GeneratedOrderRecord;
use topshelfcraft\recurringorders\orders\RecurringOrderHistoryRecord;
use topshelfcraft\recurringorders\orders\RecurringOrderRecord;

class Install extends Migration
{

	/**
	 * @inheritdoc
	 */
	public function safeUp()
	{
		return $this->_addTables();
	}

	/**
	 * @inheritdoc
	 */
	public function safeDown()
	{
		return $this->_removeTables();
	}

	/**
	 *
	 */
	private function _addTables()
	{

		/*
		 * Add Recurring Orders table
		 */

		if (!$this->db->tableExists(RecurringOrderRecord::tableName())) {

			$this->createTable(RecurringOrderRecord::tableName(), [

				'id' => $this->integer()->notNull(),

				'status' => $this->string(),
				'errorReason' => $this->string(),
				'errorCount' => $this->integer()->unsigned(),
				'recurrenceInterval' => $this->string(),
				'lastRecurrence' => $this->dateTime(),
				'nextRecurrence' => $this->dateTime(),
				'paymentSourceId' => $this->integer(),

				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),

				'PRIMARY KEY(id)',

			]);

			// Delete the Recurring Order record if the Commerce Order is deleted.

			$this->addForeignKey(
				null,
				RecurringOrderRecord::tableName(),
				['id'],
				Order::tableName(),
				['id'],
				'CASCADE'
			);

			// Wipe the Payment Source reference if the Payment Source is deleted.

			$this->addForeignKey(
				null,
				RecurringOrderRecord::tableName(),
				['paymentSourceId'],
				PaymentSource::tableName(),
				['id'],
				'SET NULL'
			);

		}

		/*
		 * Add Recurring Orders Histories table
		 */

		if (!$this->db->tableExists(RecurringOrderHistoryRecord::tableName())) {

			$this->createTable(RecurringOrderHistoryRecord::tableName(), [

				'id' => $this->primaryKey(),

				'orderId' => $this->integer()->notNull(),
				'status' => $this->string(),
				'errorReason' => $this->string(),
				'errorCount' => $this->integer()->unsigned(),
				'recurrenceInterval' => $this->string(),
				'updatedByUserId' => $this->integer(),

				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),

			]);

			// Delete the Order History record if the Recurring Order is deleted.

			$this->addForeignKey(
				null,
				RecurringOrderHistoryRecord::tableName(),
				['orderId'],
				RecurringOrderRecord::tableName(),
				['id'],
				'CASCADE'
			);

			// Wipe the Updated-By User reference if the User is deleted.

			$this->addForeignKey(
				null,
				RecurringOrderHistoryRecord::tableName(),
				['updatedByUserId'],
				User::tableName(),
				['id'],
				'SET NULL'
			);

		}

		/*
		 * Add Generated Orders table
		 */

		if (!$this->db->tableExists(GeneratedOrderRecord::tableName())) {

			$this->createTable(GeneratedOrderRecord::tableName(), [

				'id' => $this->integer()->notNull(),
				'parentOrderId' => $this->integer()->notNull(),

				'dateCreated' => $this->dateTime()->notNull(),
				'dateUpdated' => $this->dateTime()->notNull(),
				'uid' => $this->uid(),

				'PRIMARY KEY(id)',

			]);

			// Delete the Generated Order record if the Order is deleted.

			$this->addForeignKey(
				null,
				GeneratedOrderRecord::tableName(),
				['id'],
				Order::tableName(),
				['id'],
				'CASCADE'
			);

			// Delete the Generated Order record if the *parent* Order is deleted.

			$this->addForeignKey(
				null,
				GeneratedOrderRecord::tableName(),
				['parentOrderId'],
				Order::tableName(),
				['id'],
				'CASCADE'
			);

		}

	}

	private function _removeTables()
	{

		// Drop tables in reverse of the order we created them, to avoid foreign key constraint failures.

		$this->dropTableIfExists(GeneratedOrderRecord::tableName());
		$this->dropTableIfExists(RecurringOrderHistoryRecord::tableName());
		$this->dropTableIfExists(RecurringOrderRecord::tableName());

		return true;

	}

}
