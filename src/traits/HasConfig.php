<?php

namespace jorisnoo\CraftModules\traits;

use Craft;
use craft\helpers\Json;

trait HasConfig
{
    protected array $config = [];

    public function getConfigCacheKey(): string
    {
        $configFromFile = Json::encode($this->config)
            .collect($this->sources)->pluck('key')->join('');

        return $this->moduleName.'Config_'.\md5($configFromFile);
    }

    public function clearConfigCache(): void
    {
        Craft::$app->getCache()?->delete($this->getConfigCacheKey());
    }

    public function getConfig(): array
    {
        $isDevMode = Craft::$app->getConfig()->getGeneral()->devMode;
        $cacheKey = $this->getConfigCacheKey();
        $cache = Craft::$app->getCache();
        $cachedConfig = null;

        if (! $isDevMode && $cache) {
            $cachedConfig = $cache->get($cacheKey) ?? [];
        } else {
            $this->clearConfigCache();
        }

        if ($cachedConfig) {
            return $cachedConfig;
        }

        $config = $this->getCachedData();

        if (! $isDevMode && $cache) {
            $cache->set($cacheKey, $config);
        }

        return $config;
    }

}
