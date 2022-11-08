<?php
namespace TopShelfCraft\test;

use Codeception\Test\Unit;
use TopShelfCraft\RecurringOrders\orders\Orders;
use TopShelfCraft\RecurringOrders\RecurringOrders;
use TopShelfCraft\RecurringOrders\web\cp\CpCustomizations;
use UnitTester;

class RecurringOrdersTest extends Unit
{

	/**
	 * @var UnitTester
	 */
	protected $tester;

	public function testPluginHasComponents()
	{
		$this->assertInstanceOf(CpCustomizations::class, RecurringOrders::getInstance()->cpCustomizations);
		$this->assertInstanceOf(Orders::class, RecurringOrders::getInstance()->orders);
	}

}
