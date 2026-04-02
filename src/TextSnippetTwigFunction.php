<?php

namespace Noo\CraftModules;

use Craft;
use craft\elements\Entry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TextSnippetTwigFunction extends BaseModule
{
    public function attachEventHandlers(): void
    {
        if (! Craft::$app instanceof \craft\web\Application) {
            return;
        }

        Craft::$app->getView()->registerTwigExtension(new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [
                    new TwigFunction('textSnippet', [$this, 'textSnippet']),
                ];
            }

            public function textSnippet(string $handle, string $sectionName = 'translations'): ?string
            {
                $translationsEntry = Entry::find()
                    ->section($sectionName)
                    ->with([$sectionName])
                    ->one();

                return $translationsEntry?->getFieldValue($sectionName)[0][$handle] ?? null;
            }
        });
    }
}
