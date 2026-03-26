<?php

namespace Noo\CraftModules;

use Craft;
use yii\base\Module;

abstract class BaseModule extends Module
{
    public function init()
    {
        Craft::setAlias('@Noo/CraftModules', __DIR__);

        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
        });
    }

    public function attachEventHandlers(): void
    {
        // Override this method in your module class to attach event handlers
    }
}
