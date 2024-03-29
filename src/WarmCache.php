<?php

namespace jorisnoo\CraftModules;

use craft\console\controllers\InvalidateTagsController;
use craft\controllers\UtilitiesController;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\ElementHelper;
use craft\helpers\Queue;
use craft\web\UrlManager;
use jorisnoo\CraftModules\jobs\TriggerCachewarming;
use yii\base\ActionEvent;
use yii\base\Event;

class WarmCache extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $cwSiteId = App::env('CACHEWARMER_SITE_ID');
        $cwToken = App::env('CACHEWARMER_TOKEN');

        if (! $cwSiteId || ! $cwToken) {
            return;
        }

        // refresh single url after entry was saved
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                $entry = $event->sender;
                if (
                    !ElementHelper::isDraft($entry) &&
                    !($entry->duplicateOf && $entry->getIsCanonical() && !$entry->updatingFromDerivative) &&
                    ($entry->enabled && $entry->getEnabledForSite()) &&
                    !$entry->propagating &&
                    !ElementHelper::rootElement($entry)->isProvisionalDraft &&
                    !ElementHelper::isRevision($entry)
                    && $entry->url
                ) {
                    Queue::push(new TriggerCachewarming([
                        'url' => $entry->url,
                    ]));
                }
            }
        );

        // refresh all urls after template cache was cleared
        Event::on(
            InvalidateTagsController::class,
            InvalidateTagsController::EVENT_AFTER_ACTION,
            function (ActionEvent $event) {
                if ($event->sender->action->id === 'template') {
                    Queue::push(new TriggerCachewarming());
                }
            }
        );

        Event::on(
            UtilitiesController::class,
            UtilitiesController::EVENT_AFTER_ACTION,
            function (ActionEvent $event) {
                if ($event->sender->action->id === 'invalidate-tags') {
                    $tags = $event->sender->request->getBodyParam('tags');
                    if (in_array('template', $tags, true)) {
                        Queue::push(new TriggerCachewarming());
                    }
                }
            }
        );

        // register refresh url
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['api/cache-warmer/refresh'] = 'warm-cache/cache-refresh';
            }
        );
    }
}
