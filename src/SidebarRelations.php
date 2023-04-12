<?php
namespace jorisnoo\CraftModules;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\events\RegisterElementSourcesEvent;
use craft\helpers\Json;
use yii\base\Event;

class SidebarRelations extends BaseModule
{
    protected array $sources = [];
    protected array $config = [];

    public function attachEventHandlers(): void
    {
        Event::on(Entry::class, Element::EVENT_REGISTER_SOURCES, function (RegisterElementSourcesEvent $event) {

            $this->sources = $event->sources;
            $this->config = Craft::$app->getConfig()->getConfigFromFile('sidebar-relations') ?? [];
            $entrySources = $this->getConfig();

            foreach ($entrySources as $key => $nestedSources) {
                $event->sources[$key]['nested'] = $nestedSources;
            }

        });
    }

    public function getConfigCacheKey(): string
    {
        $configFromFile = Json::encode($this->config)
            .collect($this->sources)->pluck('key')->join('');
        return 'sidebarRelationsConfig_' . \md5($configFromFile);
    }

    public function clearConfigCache(): void
    {
        Craft::$app->getCache()?->delete($this->getConfigCacheKey());
    }

    public function getConfig(): array
    {
        $isDevMode = Craft::$app->getConfig()->getGeneral()->devMode;
        $cacheKey = $this->getConfigCacheKey();

        if ($isDevMode) {
            $cachedConfig = null;
            $this->clearConfigCache();
        } else {
            $cachedConfig = Craft::$app->getCache()?->get($cacheKey) ?? [];
        }

        return $cachedConfig ?? $this->getRelationSources() ?? [];
    }

    public function getRelationSources(): array
    {
        return collect($this->config)
            ->map(function ($filter) {
                $filter['sectionUid'] = Craft::$app->sections->getSectionByHandle($filter['section'])?->uid ?? null;
                return $filter;
            })
            ->filter(fn($filter) => $filter['sectionUid'])
            ->mapWithKeys(function ($filter) {

                $sourceKey = collect($this->sources)->where('key', 'section:' . $filter['sectionUid'])->keys()->first();

                if(!$sourceKey) {
                    return [];
                }

                $relatedEntries = Entry::find()->section($filter['relation']);

                if($filter['where']) {
                    foreach ($filter['where'] as $key => $condition) {
                        $relatedEntries = $relatedEntries->{$key}($condition);
                    }
                }

                $relatedEntries = $relatedEntries->limit(null)->all();

                $nestedSources = [];

                foreach ($relatedEntries as $relatedEntry) {
                    $nestedSources[] = [
                        'key' => 'related:' . $relatedEntry->uid,
                        'label' => $relatedEntry->title,
                        'data' => [
                            'has-structure' => true,
                            'default-sort' => 'structure:asc',
                            'type' => 'structure',
                            'handle' => $relatedEntry->slug,
                        ],
                        'criteria' => [
                            'sectionId' => $this->sources[$sourceKey]['criteria']['sectionId'],
                            'relatedTo' => $relatedEntry
                        ]
                    ];
                }

                return [$sourceKey => $nestedSources];
            })
            ->toArray();
    }
}