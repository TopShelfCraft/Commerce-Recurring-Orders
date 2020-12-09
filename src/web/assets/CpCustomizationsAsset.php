<?php
namespace steadfast\recurringorders\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CpCustomizationsAsset extends AssetBundle
{
	/**
	 * @inheritdoc
	 */
	public function init()
	{

		$this->sourcePath = __DIR__ . '/cpcustomizations/dist';

		$this->depends = [
//			CpAsset::class,
		];

		$this->css[] = 'css/CpCustomizations.css';

		parent::init();

	}

}
