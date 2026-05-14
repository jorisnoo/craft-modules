<?php

namespace Noo\CraftModules;

use Craft;
use craft\controllers\DashboardController;
use craft\helpers\UrlHelper;
use yii\base\ActionEvent;
use yii\base\Controller;
use yii\base\Event;

class RedirectDashboard extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! Craft::$app->getRequest()->getIsCpRequest()) {
            return;
        }

        Event::on(
            DashboardController::class,
            Controller::EVENT_BEFORE_ACTION,
            function (ActionEvent $event) {
                if ($event->action->id === 'index') {
                    $event->isValid = false;
                    Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('entries'))->send();
                }
            }
        );
    }
}
