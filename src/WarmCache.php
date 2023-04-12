<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\console\controllers\InvalidateTagsController;
use craft\helpers\App;
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

                $cwSiteId = App::env('CACHEWARMER_SITE_ID');
                $cwToken = App::env('CACHEWARMER_TOKEN');

                if(!$cwSiteId || !$cwToken) {
                    return;
                }

                // todo: run in queue job
                // https://craftcms.com/docs/4.x/extend/queue-jobs.html#writing-a-job

                // todo: refresh single url after entry was saved?

//                    @file_get_contents("https://cachewarmer.noo.dev/api/warm/{$cwSiteId}?token={$cwToken}");
            }
        );
    }
}
