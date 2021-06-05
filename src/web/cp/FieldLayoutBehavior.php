<?php
namespace beSteadfast\RecurringOrders\web\cp;

use beSteadfast\RecurringOrders\meta\RecurringOrder;
use beSteadfast\RecurringOrders\RecurringOrders;
use craft\commerce\elements\Order;
use craft\events\CreateFieldLayoutFormEvent;
use craft\fieldlayoutelements\BaseUiElement;
use craft\fieldlayoutelements\HorizontalRule;
use craft\fieldlayoutelements\Template;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use yii\base\Behavior;

class FieldLayoutBehavior extends Behavior
{

	/**
	 * @var FieldLayout
	 */
	public $owner;

	public function events()
	{
		return [
			FieldLayout::EVENT_CREATE_FORM => [$this, 'handleCreateForm'],
		];
	}

	public function handleCreateForm(CreateFieldLayoutFormEvent $event)
	{

		// If we're not in the context of a Commerce Order, bail.

		if (!($element = $event->element) instanceof Order)
		{
			return;
		}

		/** @var RecurringOrder $element */

		$uiElements = [];

		// If this is an Originating Order, show the list of Derived Orders

		if (!empty($element->getAllDerivedOrders()))
		{

			$uiElements[] = new Template([
				'template' => '__recurringOrders/cp/_orderTab/_derivedOrdersPane'
			]);

			$uiElements[] = new HorizontalRule();

		}

		// If this is a Generated Order, show the Parent Order.

		if ($element->isGenerated())
		{

			$uiElements[] = new Template([
				'template' => '__recurringOrders/cp/_orderTab/_generatedOrderPane'
			]);

			$uiElements[] = new HorizontalRule();

		}

		// If this Order is recurring, or can be made recurring, show the recurrence controls.

		if ($this->_shouldShowRecurrenceControls($element))
		{

			$uiElements[] = new Template([
				'template' => '__recurringOrders/cp/_orderTab/_recurrenceControls'
			]);

			$uiElements[] = new HorizontalRule();

		}

		// If there are Generated Orders, show the Admin Table

		if (!empty($element->getAllGeneratedOrdres()))
		{

			$uiElements[] = new Template([
				'template' => '__recurringOrders/cp/_orderTab/_generatedOrdersPane'
			]);

			$uiElements[] = new HorizontalRule();

		}

		// If there are history entries, display them.

		if (!empty($element->getRecurringOrderHistory()))
		{

			$uiElements[] = new Template([
				'template' => '__recurringOrders/cp/_orderTab/_recurringOrderHistoryPane'
			]);

		}

		// If there are UI Elements to be displayed, add a custom Field Layout tab to render them...

		$this->_trimHorizontalRule($uiElements);

		if (!empty($uiElements))
		{
			$event->tabs[] = new FieldLayoutTab([
				'name' => RecurringOrders::t('Recurring Orders'),
				'elements' => $uiElements,
			]);
		}

	}

	private function _shouldShowRecurrenceControls(Order $order): bool
	{

		/** @var RecurringOrder $order */

		$settings = RecurringOrders::getInstance()->getSettings();

		if (!empty($order->isGenerated() && $settings->hideRecurrenceControlsForGeneratedOrders))
		{
			return false;
		}

		if (!empty($order->getAllDerivedOrders() && $settings->hideRecurrenceControlsForOriginatingOrders))
		{
			return false;
		}

		if (!$order->hasRecurrenceStatus() && $settings->hideRecurrenceControlsForNonRecurringOrders)
		{
			return false;
		}

		return true;

	}

	/**
	 * If the last item in the list of UI Elements is a `HorizontalRule`, remove it.
	 *
	 * @param BaseUiElement[] $uiElements
	 */
	private function _trimHorizontalRule(array &$uiElements): void
	{

		$lastKey = array_key_last($uiElements);
		if ($uiElements[$lastKey] instanceof HorizontalRule)
		{
			array_pop($uiElements);
		}

	}

}
