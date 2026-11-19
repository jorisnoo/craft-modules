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
        if (! Craft::$app->getRequest()->getIsCpRequest()) {
            return;
        }

        Event::on(
            User::class,
            Element::EVENT_AFTER_SAVE,
            static function (ModelEvent $event) {
                /** @var User $user */
                $user = $event->sender;

                if (! $user->firstSave || $user->propagating || $user->resaving) {
                    return;
                }

                if (! $user->getIsCredentialed() || ElementHelper::isDraftOrRevision($user)) {
                    return;
                }

                $session = Craft::$app->getUser();
                $actor = $session->getIdentity();
                if (! $actor?->can('registerUsers') || ! $session->getHasElevatedSession()) {
                    return;
                }

                $group = Craft::$app->getUserGroups()->getGroupByHandle('editor');
                if (! $group || ! $actor->can("assignUserGroup:$group->uid")) {
                    return;
                }

                $groupIds = array_map(fn ($g) => $g->id, $user->getGroups());
                if (in_array($group->id, $groupIds, false)) {
                    return;
                }

                $groupIds[] = $group->id;
                Craft::$app->getUsers()->assignUserToGroups($user->id, $groupIds);
            }
        );
    }
}
