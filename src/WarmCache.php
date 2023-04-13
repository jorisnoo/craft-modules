<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\console\controllers\InvalidateTagsController;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\App;
use craft\helpers\Queue;
use craft\web\UrlManager;
use jorisnoo\CraftModules\jobs\TriggerCachewarming;
use yii\base\ActionEvent;
use yii\base\Event;

class WarmCache extends BaseModule
{
    public function attachEventHandlers(): void
    {
        // todo: refresh single url after entry was saved?

        Event::on(
            InvalidateTagsController::class,
            InvalidateTagsController::EVENT_AFTER_ACTION,
            function (ActionEvent $event) {
                Queue::push(new TriggerCachewarming());
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['api/cache-warmer/refresh'] = 'warm-cache/cache-refresh';
            }
        );
    }
}
