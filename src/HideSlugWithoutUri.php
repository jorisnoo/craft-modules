<?php

namespace Noo\CraftModules;

use craft\elements\Entry;
use craft\events\DefineMetaFields;
use yii\base\Event;

class HideSlugWithoutUri extends BaseModule
{
    public function attachEventHandlers(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_META_FIELDS,
            function (DefineMetaFields $event) {
                if (!isset($event->fields['slug'])) {
                    return;
                }

                /** @var Entry $entry */
                $entry = $event->element;

                try {
                    $uriFormat = $entry->getUriFormat();
                } catch (\Throwable) {
                    // Nested or misconfigured entries can't resolve a URI format.
                    $uriFormat = null;
                }

                if ($uriFormat === null || $uriFormat === '') {
                    unset($event->fields['slug']);
                }
            }
        );
    }
}
