<?php
namespace beSteadfast\RecurringOrders\controllers\web;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\AdminTable;
use beSteadfast\RecurringOrders\meta\RecurringOrderQuery;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CpDataController extends BaseWebController
{

	/**
	 * @throws BadRequestHttpException
	 * @throws InvalidArgumentException
	 * @throws ForbiddenHttpException
	 */
	public function actionUserOrdersTable(): Response
	{

		$this->requirePermission('commerce-manageOrders');
		$this->requireAcceptsJson();

		$request = Craft::$app->getRequest();
		$page = (int) $request->getParam('page', 1);
		$limit = (int) $request->getParam('per_page', 10);
		$search = (string) $request->getParam('search', null);
		$offset = ($page - 1) * $limit;

		$customerId = (int) $request->getQueryParam('customerId', null);

		if (!$customerId) {
			return $this->asErrorJson(Commerce::t('Customer ID is required.'));
		}

		$customer = Commerce::getInstance()->getCustomers()->getCustomerById($customerId);

		if (!$customer) {
			return $this->asErrorJson(Commerce::t('Unable to retrieve customer.'));
		}

		$orderQuery = Order::find()
			->customer($customer)
			->isCompleted()
			->hasRecurrenceStatus();

		if ($search) {
			$orderQuery->search($search);
		}

		$total = $orderQuery->count();

		$orderQuery->offset($offset);
		$orderQuery->limit($limit);
		$orderQuery->orderBy('dateOrdered DESC');
		$orders = $orderQuery->all();

		$rows = [];
		foreach ($orders as $order) {

			/** @var Order $order */

			$rows[] = [
				'id' => $order->id,
				'title' => $order->reference,
				'url' => $order->getCpEditUrl(),
				'date' => $order->dateOrdered->format('D jS M Y'),
				'totalPrice' => Craft::$app->getFormatter()->asCurrency($order->getTotalPrice(), $order->currency, [], [], false),
				'orderStatus' => $order->getOrderStatusHtml(),
				'recurrenceStatus' => $order->getTableAttributeHtml('recurrenceStatus'),
				'nextRecurrence' => $order->getTableAttributeHtml('nextRecurrence'),
				'lastRecurrence' => $order->getTableAttributeHtml('lastRecurrence'),
			];
		}

		return $this->asJson([
			'pagination' => AdminTable::paginationLinks($page, $total, $limit),
			'data' => $rows,
		]);

	}

	/**
	 * @throws BadRequestHttpException
	 * @throws InvalidArgumentException
	 * @throws ForbiddenHttpException
	 */
	public function actionGeneratedOrdersTable(): Response
	{

		$this->requirePermission('commerce-manageOrders');
		$this->requireAcceptsJson();

		$request = Craft::$app->getRequest();
		$page = (int) $request->getParam('page', 1);
		$limit = (int) $request->getParam('per_page', 10);
		$search = (string) $request->getParam('search', null);
		$offset = ($page - 1) * $limit;

		$parentId = (int) $request->getQueryParam('parentId', null);

		if (!$parentId) {
			return $this->asErrorJson(Commerce::t('Parent Order ID is required.'));
		}

		/** @var RecurringOrderQuery $orderQuery */
		$orderQuery = Order::find()
			->isCompleted()
			->parentOrderId($parentId);

		if ($search) {
			$orderQuery->search($search);
		}

		$total = $orderQuery->count();

		$orderQuery->offset($offset);
		$orderQuery->limit($limit);
		$orderQuery->orderBy('dateOrdered DESC');
		$orders = $orderQuery->all();

		$rows = [];
		foreach ($orders as $order) {

			/** @var Order $order */

			$rows[] = [
				'id' => $order->id,
				'title' => $order->reference,
				'url' => $order->getCpEditUrl(),
				'date' => $order->dateOrdered->format('D jS M Y'),
				'totalPrice' => Craft::$app->getFormatter()->asCurrency($order->getTotalPrice(), $order->currency, [], [], false),
				'orderStatus' => $order->getOrderStatusHtml(),
			];
		}

		return $this->asJson([
			'pagination' => AdminTable::paginationLinks($page, $total, $limit),
			'data' => $rows,
		]);

	}

}
