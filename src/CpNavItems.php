<?php

namespace Noo\CraftModules;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use yii\base\Event;

class CpNavItems extends BaseModule
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
                foreach ($event->navItems as $key => &$item) {
                    $url = $item['url'] ?? '';
                    if ($url === 'ohdear') {
                        unset($item['icon']);
                        $item['fontIcon'] = 'gauge';
                    } elseif ($url === 'dashboard') {
                        unset($event->navItems[$key]);
                    }
                }
                unset($item);
            }
        );
    }
}
