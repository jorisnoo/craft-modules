<?php

namespace jorisnoo\CraftModules;

use Craft;
use craft\controllers\UsersController;
use craft\events\UserEvent;
use yii\base\Event;

class MakeUsersEditors extends BaseModule
{
    public function attachEventHandlers(): void
    {
        Event::on(
            UsersController::class,
            UsersController::EVENT_AFTER_ASSIGN_GROUPS_AND_PERMISSIONS,
            static function (UserEvent $event) {
                $editorGroupId = Craft::$app->getUserGroups()->getGroupByHandle('editor')->id ?? null;
                if ($editorGroupId) {
                    Craft::$app->getUsers()->assignUserToGroups($event->user->id, [$editorGroupId]);
                }
            }
        );
    }
}
