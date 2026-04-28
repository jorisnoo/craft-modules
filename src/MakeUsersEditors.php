<?php

namespace Noo\CraftModules;

use Craft;
use craft\base\Element;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\helpers\ElementHelper;
use yii\base\Event;

class MakeUsersEditors extends BaseModule
{
    public function attachEventHandlers(): void
    {
        Event::on(
            User::class,
            Element::EVENT_AFTER_SAVE,
            static function (ModelEvent $event) {
                /** @var User $user */
                $user = $event->sender;

                if (!$user->firstSave || $user->propagating || $user->resaving) {
                    return;
                }

                if (ElementHelper::isDraftOrRevision($user)) {
                    return;
                }

                $editorGroupId = Craft::$app->getUserGroups()->getGroupByHandle('editor')?->id;
                if (!$editorGroupId) {
                    return;
                }

                $groupIds = array_map(fn($g) => $g->id, $user->getGroups());
                if (in_array($editorGroupId, $groupIds, false)) {
                    return;
                }

                $groupIds[] = $editorGroupId;
                Craft::$app->getUsers()->assignUserToGroups($user->id, $groupIds);
            }
        );
    }
}
