<?php
namespace beSteadfast\RecurringOrders\web\assets;

use craft\commerce\web\assets\statwidgets\StatWidgetsAsset;
use craft\web\AssetBundle;
use craft\web\assets\admintable\AdminTableAsset;
use craft\web\assets\cp\CpAsset;

class OrdersWidgetAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {

        $this->depends = [
            CpAsset::class,
            StatWidgetsAsset::class,
            AdminTableAsset::class,
			CpCustomizationsAsset::class,
        ];

        parent::init();

    }

}
