<?php

namespace jorisnoo\CraftModules;

use craft\events\ReplaceAssetEvent;
use craft\helpers\App;
use craft\services\Assets;
use GuzzleHttp\Client;
use yii\base\Event;
use yii\base\Module;

class PurgeReplacedFiles extends Module
{
    public function wip()
    {
        Event::on(Assets::class,
            Assets::EVENT_BEFORE_REPLACE_ASSET,
            function (ReplaceAssetEvent $event) {

                $bossApiKey = App::env('IMAGEBOSS_API_KEY');
                $bossSource = App::env('IMAGEBOSS_SOURCE');

                if(!$bossApiKey || !$bossSource) {
                    return;
                }

                $parts = explode('/', $event->asset->getVolume()->getFs()->path);
                $volumeFolder = end($parts);
                $requestUrl = "https://img.imageboss.me/{$bossSource}/{$volumeFolder}/{$event->asset->path}";

//                $client = new Client();
//                $response = $client->request('DELETE', $requestUrl, [
//                    'headers' => [
//                        'imageboss-api-key' => $bossApiKey,
//                    ]
//                ]);
//
//                ray($response->getHeaders());

            }
        );
    }
}
