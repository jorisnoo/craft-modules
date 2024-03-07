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

    public function getDescription(): string
    {
        return 'Triggering Cache Refresh for '.$this->url;
    }

    public function execute($queue): void
    {
        // do not abort if template caching is disabled, cuz we want to preload them images
        //if (! Craft::$app->getConfig()->getGeneral()->enableTemplateCaching) {
        //    return;
        //}

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
