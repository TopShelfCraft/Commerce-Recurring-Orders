<?php
namespace TopShelfCraft\test;

use Craft;
use Codeception\Test\Unit;
use UnitTester;

class InstallationTest extends Unit
{

	/**
	 * @var UnitTester
	 */
	protected $tester;

	public function testEditionIsPro()
	{
		Craft::$app->setEdition(Craft::Pro);

		$this->assertSame(
			Craft::Pro,
			Craft::$app->getEdition());
	}

	public function testCommerceIsInstalled()
	{
		$this->assertTrue(
			Craft::$app->plugins->getPlugin('commerce')->isInstalled
		);
	}

	public function testRecurringOrdersIsInstalled()
	{
		$this->assertTrue(
			Craft::$app->plugins->getPlugin('recurring-orders')->isInstalled
		);
	}

}
