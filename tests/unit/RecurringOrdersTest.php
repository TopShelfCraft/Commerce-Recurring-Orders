<?php

namespace steadfast\tests;

use Craft;
use Codeception\Test\Unit;
use steadfast\recurringorders\orders\Orders;
use steadfast\recurringorders\RecurringOrders;
use steadfast\recurringorders\web\cp\CpCustomizations;
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
