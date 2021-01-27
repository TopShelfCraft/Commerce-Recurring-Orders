<?php
namespace beSteadfast\test;

use Codeception\Test\Unit;
use beSteadfast\RecurringOrders\orders\Orders;
use beSteadfast\RecurringOrders\RecurringOrders;
use beSteadfast\RecurringOrders\web\cp\CpCustomizations;
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
