<?php

namespace Noo\CraftModules;

use Craft;
use yii\base\Event;
use yii\queue\Queue;

class TextSnippetTwigFunction extends BaseModule
{
    public function attachEventHandlers(): void
    {
        $extension = new TextSnippetTwigExtension;
        Craft::$app->getView()->registerTwigExtension($extension);
        Event::on(Queue::class, Queue::EVENT_BEFORE_EXEC, [$extension, 'reset']);
    }
}
