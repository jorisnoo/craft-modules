<?php

namespace Noo\CraftModules;

use Craft;
use craft\helpers\App;
use craft\web\View;
use yii\base\Event;

class EnvironmentIndicator extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $environment = App::env('CRAFT_ENVIRONMENT');

        if ($environment === 'production' || ! Craft::$app->getRequest()->getIsCpRequest()) {
            return;
        }

        $color = $environment === 'dev' ? '#00d0ff' : '#f3b737';

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function () use ($color) {
                if (! Craft::$app->getUser()->getIdentity()) {
                    return;
                }

                Craft::$app->getView()->registerCss(
                    <<<CSS
                    .global-sidebar__header {
                        position: relative;
                        background-color: {$color};
                    }
                    .global-sidebar__header::after {
                        content: '⚠️';
                        position: absolute;
                        right: 10px;
                        top: 50%;
                        transform: translateY(-50%);
                        font-size: 18px;
                        line-height: 1;
                    }
                    CSS
                );
            }
        );
    }
}
