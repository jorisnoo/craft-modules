<?php

namespace Noo\CraftModules;

use Craft;
use craft\elements\Entry;
use craft\base\ElementInterface;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig derives compiled-template class hashes from the extension set's class
 * names, so this must stay a named class: an anonymous class gets a new name
 * on every request, which forces Twig to recompile every template per request
 * and breaks implicit {% cache %} keys.
 */
class TextSnippetTwigExtension extends AbstractExtension
{
    /** @var array<string, Entry[]> Per-request cache of a section's entries, keyed by "section|siteId". */
    private array $entries = [];

    /** @var array<string, ElementInterface|null> Per-request cache of legacy Matrix blocks, keyed by "entryId|siteId|field". */
    private array $legacyBlocks = [];

    public function getFunctions(): array
    {
        return [
            new TwigFunction('textSnippet', [$this, 'textSnippet']),
        ];
    }

    /**
     * Returns a text snippet stored on the single (or one of the entries) of a section.
     *
     * The snippet may live on any entry type within the section, so the lookup walks
     * the section's entries and returns the value from whichever one carries the handle.
     * Pass $entryType to check that entry type first; it still falls back to the others.
     */
    public function textSnippet(string $handle, string $sectionName = 'translations', ?string $entryType = null): string|Markup|null
    {
        $entries = $this->sectionEntries($sectionName);

        // Prefer the explicitly requested entry type, then fall back to the rest.
        if ($entryType !== null) {
            usort($entries, fn (Entry $a, Entry $b) => (
                ($b->getType()?->handle === $entryType) <=> ($a->getType()?->handle === $entryType)
            ));
        }

        foreach ($entries as $entry) {
            $value = $this->snippetFromEntry($entry, $handle, $sectionName);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function snippetFromEntry(Entry $entry, string $handle, string $sectionName): string|Markup|null
    {
        $layout = $entry->getFieldLayout();

        // Flat layout: a direct field-layout instance carries the snippet.
        if ($layout?->getFieldByHandle($handle) !== null) {
            return $this->toSnippet($entry->getFieldValue($handle));
        }

        // Legacy layout: a single Matrix block (field handle == section name)
        // holds the snippets as its sub-fields.
        if ($layout?->getFieldByHandle($sectionName) !== null) {
            $block = $this->legacyBlock($entry, $sectionName);

            if ($block && $block->getFieldLayout()?->getFieldByHandle($handle) !== null) {
                return $this->toSnippet($block->getFieldValue($handle));
            }
        }

        return null;
    }

    /**
     * The legacy Matrix field returns a lazy EntryQuery, so resolve its first
     * block once per entry instead of querying on every textSnippet() call.
     */
    private function legacyBlock(Entry $entry, string $fieldHandle): ?ElementInterface
    {
        $key = "$entry->id|$entry->siteId|$fieldHandle";

        if (! array_key_exists($key, $this->legacyBlocks)) {
            $block = $entry->getFieldValue($fieldHandle)[0] ?? null;

            $this->legacyBlocks[$key] = $block instanceof ElementInterface ? $block : null;
        }

        return $this->legacyBlocks[$key];
    }

    /**
     * Rich-text fields (CKEditor, Redactor) hand back Twig\Markup value
     * objects; keep them as Markup so their HTML renders without |raw.
     * Other values are returned as plain strings and escaped as usual.
     */
    private function toSnippet(mixed $value): string|Markup|null
    {
        if (! is_string($value) && ! $value instanceof \Stringable) {
            return null;
        }

        $string = (string) $value;

        if ($string === '') {
            return null;
        }

        return $value instanceof Markup ? new Markup($string, 'UTF-8') : $string;
    }

    /** @return Entry[] */
    private function sectionEntries(string $sectionName): array
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $key = "$sectionName|$siteId";

        if (! array_key_exists($key, $this->entries)) {
            $this->entries[$key] = Entry::find()
                ->section($sectionName)
                ->siteId($siteId)
                ->all();
        }

        return $this->entries[$key];
    }
}
