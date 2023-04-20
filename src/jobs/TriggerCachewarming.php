<?php

namespace jorisnoo\CraftModules\jobs;

use Craft;
use craft\helpers\App;
use craft\queue\BaseJob;
use GuzzleHttp\Client;

class TriggerCachewarming extends BaseJob
{
    public string $url = '';

    public const CW_URL = 'https://cachewarmer.app';

    public function execute($queue): void
    {
        // abort if template caching is disabled
        if (! Craft::$app->getConfig()->getGeneral()->enableTemplateCaching) {
            return;
        }

        if (Craft::$app->env === 'dev') {
            return;
        }

        $cwSiteId = App::env('CACHEWARMER_SITE_ID');
        $cwToken = App::env('CACHEWARMER_TOKEN');

        if (! $cwSiteId || ! $cwToken) {
            return;
        }

        $client = new Client();
        $client->post(self::CW_URL."/api/warm/{$cwSiteId}", [
            'form_params' => [
                'url' => $this->url,
            ],
            'headers' => [
                'Authorization' => 'Bearer '.$cwToken,
            ],
        ]);
    }
}
