<?php

namespace Noo\CraftModules;

use Craft;
use yii\base\Module;

class CraftModules extends Module
{
    public function init(): void
    {
        Craft::setAlias('@Noo/CraftModules', __DIR__);

        parent::init();

        Craft::$app->onInit(function () {
            $modules = [
                new AnalyticsNavLink(),
                new CpCss(),
                new CpNavItems(),
                new EnvironmentIndicator(),
                new FlareExceptionFilter(),
                new HideUserPermissions(),
                new MakeUsersEditors(),
            ];

            foreach ($modules as $module) {
                $module->attachEventHandlers();
            }
        });
    }
}
