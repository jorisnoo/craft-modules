<?php

namespace Noo\CraftModules;

use Craft;
use craft\web\Application;

class TextSnippetTwigFunction extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! Craft::$app instanceof Application) {
            return;
        }

        Craft::$app->getView()->registerTwigExtension(new TextSnippetTwigExtension);
    }
}
