<?php
namespace topshelfcraft\recurringorders\misc;

use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\models\PaymentSource;
use craft\commerce\Plugin as Commerce;

class PaymentSourcesHelper
{

	/**
	 * @param Order $order
	 *
	 * @return PaymentSource[]
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	public static function getPaymentSourcesByOrder(Order $order)
	{

		$user = $order->getUser();
		if (!$user)
		{
			return [];
		}

		return Commerce::getInstance()->paymentSources->getAllPaymentSourcesByUserId($user->id);

	}

	/**
	 * @param Order $order
	 *
	 * @return array
	 *
	 * @throws \yii\base\InvalidConfigException
	 */
	public static function getPaymentSourceFormOptionsByOrder(Order $order)
	{

		$sources = static::getPaymentSourcesByOrder($order);

		if (empty($sources))
		{
			// TODO: Translate.
			return [
				'none' => [
					'label' => '(No Payment Sources exist for this User.)',
					'value' => '',
					'disabled' => true,
				]
			];
		}

		// TODO: Translate.
		$options = [
			'none' => [
				'label' => '',
				'value' => '',
			]
		];

		foreach ($sources as $source)
		{
			$options[$source->id] = $source->description;
		}

		return $options;

	}

}
