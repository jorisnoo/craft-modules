<?php

namespace Noo\CraftModules;

use craft\controllers\UsersController;
use craft\events\DefineEditUserScreensEvent;
use yii\base\Event;

class HideUserPermissions extends BaseModule
{
    public function attachEventHandlers(): void
    {
        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            static function (DefineEditUserScreensEvent $event) {
                unset($event->screens['permissions']);
            }
        );
    }
}
