<?php

namespace jorisnoo\CraftModules;

use Craft;
use craft\base\Element;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\RegisterElementSourcesEvent;
use jorisnoo\CraftModules\traits\HasConfig;
use yii\base\Event;

class SidebarRelations extends BaseModule
{
    use HasConfig;

    protected string $configFile = 'sidebar-relations';
    protected array $sources = [];

    public function attachEventHandlers(): void
    {
        Event::on(Entry::class, Element::EVENT_REGISTER_SOURCES, function (RegisterElementSourcesEvent $event) {

            $this->sources = $event->sources;
            $this->config = $this->getConfig();
            $entrySources = $this->getCachedData();

            foreach ($entrySources as $key => $nestedSources) {
                $event->sources[$key]['nested'] = $nestedSources;
            }
        });
    }

    public function moduleData(): array
    {
        return collect($this->config)
            ->map(function ($filter) {
                $filter['sectionUid'] = Craft::$app->sections->getSectionByHandle($filter['section'])?->uid ?? null;

                return $filter;
            })
            ->filter(fn ($filter) => $filter['sectionUid'])
            ->mapWithKeys(function ($filter) {

                $sourceKey = collect($this->sources)->where('key', 'section:'.$filter['sectionUid'])->keys()->first();

                if (! $sourceKey) {
                    return [];
                }

                $relationType = $filter['relationType'] ?? Entry::class;

                $query = match ($relationType) {
                    Category::class => 'group',
                    default => 'section',
                };

                $relatedEntries = $relationType::find()->{$query}($filter['relation']);

                if (isset($filter['where']) && is_array($filter['where'])) {
                    foreach ($filter['where'] as $key => $condition) {
                        $relatedEntries = $relatedEntries->{$key}($condition);
                    }
                }

                $relatedEntries = $relatedEntries->limit(null)->all();

                if (count($relatedEntries) < 2) {
                    return [];
                }

                $nestedSources = [];

                foreach ($relatedEntries as $relatedEntry) {
                    $nestedSources[] = [
                        'key' => 'related:'.$relatedEntry->uid,
                        'label' => $relatedEntry->title,
                        'data' => [
                            'has-structure' => true,
                            'default-sort' => 'structure:asc',
                            'type' => 'structure',
                            'handle' => $relatedEntry->slug,
                        ],
                        'criteria' => [
                            'sectionId' => $this->sources[$sourceKey]['criteria']['sectionId'],
                            'relatedTo' => [
                                'targetElement' => $relatedEntry,
                                ...isset($filter['field']) ? ['field' => $filter['field']] : [],
                            ],
                        ],
                    ];
                }

                return [$sourceKey => $nestedSources];
            })
            ->filter()
            ->toArray();
    }
}
