<?php

namespace steadfast\test\orders;

use Craft;
use Codeception\Test\Unit;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\models\Customer;
use craft\commerce\Plugin as Commerce;
use craft\commerce\test\fixtures\elements\ProductFixture;
use steadfast\recurringorders\RecurringOrders;
use UnitTester;

class OrdersTest extends Unit
{

	/**
	 * @var Customer
	 */
	protected $customer;

	/**
	 * @var ProductFixture
	 */
	protected $productData;

	/**
	 * @var UnitTester
	 */
	protected $tester;

	/**
	 * @return array
	 */
	public function _fixtures(): array
	{
		return [
			'products' => [
				'class' => ProductFixture::class,
				'tableName' => 'commerce_products'
			],
		];
	}

	/**
	 *
	 */
	protected function _before()
	{

		parent::_before();

		Commerce::getInstance()->edition = Commerce::EDITION_PRO;

		$this->productData = $this->tester->grabFixture('products');

		$this->customer = new Customer();
		Commerce::getInstance()->customers->saveCustomer($this->customer);

	}

	public function testLightCloneMatchesOriginalOrder()
	{

		$purchasable = Product::findOne()->getDefaultVariant();
		/** @var Product $product */

		$originalOrder = new Order();
		$originalOrder->setCustomer($this->customer);
		Craft::$app->elements->saveElement($originalOrder);
		$lineItem1 = Commerce::getInstance()->lineItems->createLineItem(
			$originalOrder->id,
			$purchasable->id,
			[],
			3,
			'',
			$originalOrder
		);
		$originalOrder->addLineItem($lineItem1);
		Craft::$app->elements->saveElement($originalOrder);

		$clone = RecurringOrders::getInstance()->orders->getLightClone($originalOrder);

		$this->assertEquals($originalOrder->getTotalQty(), $clone->getTotalQty(), "Clone order quantity doesn't match Original order quantity.");
		$this->assertEquals($clone->getTotalQty(), 3, "Clone order has unexpected Total Quantity.");
		$this->assertEquals($originalOrder->getTotal(), $clone->getTotal(), "Clone order total doesn't match Original order total.");
		$this->assertEquals($clone->getItemTotal(), $purchasable->getPrice()*3, "Clone order has unexpected Item Total.");

	}

}
