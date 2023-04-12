<?php

namespace jorisnoo\CraftModules\Jobs;

use craft\helpers\App;
use craft\queue\BaseJob;

class TriggerCachewarming extends BaseJob
{
    public function execute($queue): void
    {
        $cwSiteId = App::env('CACHEWARMER_SITE_ID');
        $cwToken = App::env('CACHEWARMER_TOKEN');

        if(!$cwSiteId || !$cwToken) {
            return;
        }

        @file_get_contents("https://cachewarmer.noo.dev/api/warm/{$cwSiteId}?token={$cwToken}");
    }
}
