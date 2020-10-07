<?php

namespace steadfast\tests;

use Craft;
use Codeception\Test\Unit;
use topshelfcraft\recurringorders\orders\Orders;
use topshelfcraft\recurringorders\RecurringOrders;
use topshelfcraft\recurringorders\web\cp\CpCustomizations;
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
