<?php

namespace jorisnoo\CraftModules;

use Craft;
use yii\base\Module;

abstract class BaseModule extends Module
{
    public function init()
    {
        Craft::setAlias('@jorisnoo/CraftModules', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'jorisnoo\\CraftModules\\console\\controllers';
        } else {
            $this->controllerNamespace = 'jorisnoo\\CraftModules\\controllers';
        }

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
