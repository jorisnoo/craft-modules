<?php

namespace Noo\CraftModules;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use yii\base\Event;

class AnalyticsNavLink extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! Craft::$app->getRequest()->getIsCpRequest()) {
            return;
        }

        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function (RegisterCpNavItemsEvent $event) {
                $plausibleUrl = Craft::$app->config->custom->plausible_url ?? null;
                if ($plausibleUrl) {
                    $event->navItems[] = [
                        'label' => 'Analytics',
                        'url' => $plausibleUrl,
                        'fontIcon' => 'eye',
                        'external' => true,
                    ];
                }
            }
        );
    }
}
