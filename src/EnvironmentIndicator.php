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

        [$color, $hoverColor] = $environment === 'dev'
            ? ['#00d0ff', '#00b8e0']
            : ['#f3b737', '#e0a520'];

        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
            function () use ($color, $hoverColor) {
                if (! Craft::$app->getUser()->getIdentity()) {
                    return;
                }

                Craft::$app->getView()->registerCss(
                    <<<CSS
                    a#system-info {
                        position: relative;
                        background-color: {$color};
                    }
                    a#system-info:hover {
                        background-color: {$hoverColor};
                    }
                    body[data-sidebar="collapsed"] a#system-info::after {
                        display: none;
                    }
                    a#system-info::after {
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
