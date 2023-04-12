<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\console\controllers\InvalidateTagsController;
use craft\helpers\App;
use craft\helpers\Queue;
use jorisnoo\CraftModules\Jobs\TriggerCachewarming;
use yii\base\ActionEvent;
use yii\base\Event;

class WarmCache extends BaseModule
{
    public function attachEventHandlers(): void
    {

        Event::on(
            InvalidateTagsController::class,
            InvalidateTagsController::EVENT_AFTER_ACTION,
            function (ActionEvent $event) {

                // abort if template caching is disabled
                if (!Craft::$app->getConfig()->getGeneral()->enableTemplateCaching) {
                    return;
                }

                if(Craft::$app->env === 'dev') {
                    return;
                }

                // run in queue job
                Queue::push(new TriggerCachewarming());

                // todo: refresh single url after entry was saved?
            }
        );
    }
}
