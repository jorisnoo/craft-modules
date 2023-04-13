<?php

namespace jorisnoo\CraftModules\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;

class TriggerCachewarming extends BaseJob
{
    public function execute($queue): void
    {
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

        @file_get_contents("https://cachewarmer.noo.dev/api/warm/{$cwSiteId}?token={$cwToken}");
    }
}
