<?php

namespace Noo\CraftModules;

use Craft;
use yii\base\Module;

class CraftModules extends Module
{
    public function init(): void
    {
        Craft::setAlias('@Noo/CraftModules', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'Noo\\CraftModules\\console\\controllers';
        }

        parent::init();

        Craft::$app->setModules([
            'craft-sitemap' => \Noo\CraftSitemap\Module::class,
            'remote-sync' => \Noo\CraftRemoteSync\Module::class,
        ]);
        Craft::$app->getModule('craft-sitemap');
        Craft::$app->getModule('remote-sync');

        Craft::$app->onInit(function () {
            $modules = [
                new AnalyticsNavLink(),
                new CpCss(),
                new CpNavItems(),
                new EnvironmentIndicator(),
                new FlareExceptionFilter(),
                new HideUserPermissions(),
                new MakeUsersEditors(),
                new TextSnippetTwigFunction(),
            ];

            foreach ($modules as $module) {
                $module->attachEventHandlers();
            }
        });
    }
}
