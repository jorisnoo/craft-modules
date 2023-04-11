<?php
namespace jorisnoo\CraftModules;

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

                $cwSiteId = App::env('CACHEWARMER_SITE_ID');
                $cwToken = App::env('CACHEWARMER_TOKEN');

                if($cwSiteId && $cwToken) {
                    @file_get_contents("https://cachewarmer.noo.dev/api/warm/{$cwSiteId}?token={$cwToken}");
                }
            }
        );
    }
}
