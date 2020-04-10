<?php
namespace topshelfcraft\recurringorders\controllers\web;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\helpers\AdminTable;
use topshelfcraft\recurringorders\orders\RecurringOrderQueryBehavior;
use yii\base\InvalidArgumentException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class CpDataController extends BaseWebController
{

	/**
	 * @return Response
	 *
	 * @throws BadRequestHttpException
	 * @throws InvalidArgumentException
	 * @throws ForbiddenHttpException
	 */
	public function actionUserOrdersTable(): Response
	{
		$this->requirePermission('commerce-manageOrders');
		$this->requireAcceptsJson();

		$request = Craft::$app->getRequest();
		$page = $request->getParam('page', 1);
		$sort = $request->getParam('sort', null);
		$limit = $request->getParam('per_page', 10);
		$search = $request->getParam('search', null);
		$offset = ($page - 1) * $limit;

		$customerId = $request->getQueryParam('customerId', null);

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

		if ($sort) {
			list($field, $direction) = explode('|', $sort);

			if ($field && $direction) {
				$orderQuery->orderBy($field . ' ' . $direction);
			}
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
				'total' => Craft::$app->getFormatter()->asCurrency($order->getTotalPaid(), $order->currency, [], [], false),
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
	 * @return Response
	 *
	 * @throws BadRequestHttpException
	 * @throws InvalidArgumentException
	 * @throws ForbiddenHttpException
	 */
	public function actionGeneratedOrdersTable(): Response
	{

		$this->requirePermission('commerce-manageOrders');
		$this->requireAcceptsJson();

		$request = Craft::$app->getRequest();
		$page = $request->getParam('page', 1);
		$sort = $request->getParam('sort', null);
		$limit = $request->getParam('per_page', 10);
		$search = $request->getParam('search', null);
		$offset = ($page - 1) * $limit;

		$parentId = $request->getQueryParam('parentId', null);

		if (!$parentId) {
			return $this->asErrorJson(Commerce::t('Parent Order ID is required.'));
		}

		$parentOrder = Commerce::getInstance()->getOrders()->getOrderById($parentId);

		if (!$parentOrder) {
			return $this->asErrorJson(Commerce::t('Unable to retrieve the Parent Order.'));
		}

		/** @var RecurringOrderQueryBehavior $orderQuery */

		$orderQuery = Order::find()
			->isCompleted()
			->parentOrderId($parentOrder->id);

		if ($search) {
			$orderQuery->search($search);
		}

		if ($sort) {
			list($field, $direction) = explode('|', $sort);

			if ($field && $direction) {
				$orderQuery->orderBy($field . ' ' . $direction);
			}
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
				'total' => Craft::$app->getFormatter()->asCurrency($order->getTotalPaid(), $order->currency, [], [], false),
				'orderStatus' => $order->getOrderStatusHtml(),
			];
		}

		return $this->asJson([
			'pagination' => AdminTable::paginationLinks($page, $total, $limit),
			'data' => $rows,
		]);
	}

}
