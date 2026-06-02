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
            /** @var array<string, Entry|null> Per-request cache keyed by "section|siteId". */
            private array $entries = [];

            public function getFunctions(): array
            {
                return [
                    new TwigFunction('textSnippet', [$this, 'textSnippet']),
                ];
            }

            public function textSnippet(string $handle, string $sectionName = 'translations'): ?string
            {
                $entry = $this->sectionEntry($sectionName);

                if (! $entry) {
                    return null;
                }

                $layout = $entry->getFieldLayout();

                // Flat layout: a direct field-layout instance carries the snippet.
                if ($layout?->getFieldByHandle($handle) !== null) {
                    $value = $entry->getFieldValue($handle);

                    return is_string($value) && $value !== '' ? $value : null;
                }

                // Legacy layout: a single Matrix block (field handle == section name)
                // holds the snippets as its sub-fields.
                if ($layout?->getFieldByHandle($sectionName) !== null) {
                    $block = $entry->getFieldValue($sectionName)[0] ?? null;

                    if ($block && $block->getFieldLayout()?->getFieldByHandle($handle) !== null) {
                        $value = $block->getFieldValue($handle);

                        return is_string($value) && $value !== '' ? $value : null;
                    }
                }

                return null;
            }

            private function sectionEntry(string $sectionName): ?Entry
            {
                $siteId = Craft::$app->getSites()->getCurrentSite()->id;
                $key = "$sectionName|$siteId";

                if (! array_key_exists($key, $this->entries)) {
                    $this->entries[$key] = Entry::find()
                        ->section($sectionName)
                        ->siteId($siteId)
                        ->one();
                }

                return $this->entries[$key];
            }
        });
    }
}
