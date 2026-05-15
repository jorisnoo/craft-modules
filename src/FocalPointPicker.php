<?php

namespace Noo\CraftModules;

use craft\events\DefineFieldLayoutElementsEvent;
use craft\models\FieldLayout;
use Noo\CraftModules\fieldlayoutelements\FocalPointUiElement;
use yii\base\Event;

class FocalPointPicker extends BaseModule
{
    public function attachEventHandlers(): void
    {
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_DEFINE_UI_ELEMENTS,
            function (DefineFieldLayoutElementsEvent $event) {
                $event->elements[] = FocalPointUiElement::class;
            }
        );
    }
}
