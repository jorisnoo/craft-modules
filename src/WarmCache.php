<?php
namespace jorisnoo\CraftModules;

use craft\console\controllers\InvalidateTagsController;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Queue;
use craft\web\UrlManager;
use jorisnoo\CraftModules\jobs\TriggerCachewarming;
use yii\base\ActionEvent;
use yii\base\Event;

class WarmCache extends BaseModule
{
    public function attachEventHandlers(): void
    {
        // refresh single url after entry was saved
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                if ($event->sender->enabled &&  $event->sender->url && $event->sender->getEnabledForSite() && !$event->sender->getIsRevision()) {
                    Queue::push(new TriggerCachewarming([
                        'url' => $event->sender->url,
                    ]));
                }
            }
        );

        // refresh all urls after cache was cleared
        Event::on(
            InvalidateTagsController::class,
            InvalidateTagsController::EVENT_AFTER_ACTION,
            function (ActionEvent $event) {
                Queue::push(new TriggerCachewarming());
            }
        );

        // register refresh url
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['api/cache-warmer/refresh'] = 'warm-cache/cache-refresh';
            }
        );
    }
}
