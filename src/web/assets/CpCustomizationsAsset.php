<?php
namespace TopShelfCraft\RecurringOrders\web\assets;

use craft\web\AssetBundle;

class CpCustomizationsAsset extends AssetBundle
{

	/**
	 * @inheritdoc
	 */
	public function init()
	{

		$this->sourcePath = __DIR__ . '/cpcustomizations/dist';

		$this->css[] = 'css/CpCustomizations.css';

		parent::init();

	}

}
