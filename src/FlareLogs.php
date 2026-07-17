<?php

namespace Noo\CraftModules;

use Craft;
use craft\helpers\App;
use Noo\CraftModules\log\FlareTarget;

class FlareLogs extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $apiKey = App::env('FLARE_KEY');

        if (! is_string($apiKey) || $apiKey === '') {
            return;
        }

        Craft::$app->getLog()->targets['flare'] = Craft::createObject([
            'class' => FlareTarget::class,
            'apiKey' => $apiKey,
            'serviceName' => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
            'serviceStage' => App::env('CRAFT_ENVIRONMENT'),
            'levels' => FlareTarget::levelsFor(App::env('FLARE_LOG_LEVEL') ?: 'warning'),
            'exportInterval' => 100,
        ]);
    }
}
