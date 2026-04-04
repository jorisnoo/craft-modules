<?php

namespace Noo\CraftModules;

use Craft;
use craft\queue\Queue;
use yii\base\Event;
use yii\base\Module;
use yii\queue\ExecEvent;

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
            'twig-helpers' => \Noo\CraftTwigHelpers\TwigHelpers::class,
        ]);
        Craft::$app->getModule('craft-sitemap');
        Craft::$app->getModule('remote-sync');
        Craft::$app->getModule('twig-helpers');


        if (class_exists(\webhubworks\ohdear\health\jobs\QueueHealthJob::class)) {
            Event::on(
                Queue::class,
                Queue::EVENT_AFTER_ERROR,
                function (ExecEvent $event) {
                    if ($event->job instanceof \webhubworks\ohdear\health\jobs\QueueHealthJob) {
                        Craft::$app->getQueue()->release($event->id);
                    }
                }
            );
        }

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
