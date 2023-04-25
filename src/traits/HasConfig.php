<?php

namespace jorisnoo\CraftModules\traits;

use Craft;
use craft\helpers\Json;

trait HasConfig
{
    protected array $config = [];

    public function getConfig(): array
    {
        return Craft::$app->getConfig()->getConfigFromFile($this->configFile) ?? [];
    }

    public function getCacheKey(): string
    {
        $configFromFile = Json::encode($this->config)
            .collect($this->sources)->pluck('key')->join('');

        return $this->configFile.'_Config_'.\md5($configFromFile);
    }

    public function clearCachedData(): void
    {
        Craft::$app->getCache()?->delete($this->getCacheKey());
    }

    public function getCachedData()
    {
        $isDevMode = Craft::$app->getConfig()->getGeneral()->devMode;
        $cacheKey = $this->getCacheKey();
        $cache = Craft::$app->getCache();
        $cachedConfig = null;

        if (! $isDevMode && $cache) {
            $cachedConfig = $cache->get($cacheKey) ?? [];
        } else {
            $this->clearCachedData();
        }

        if ($cachedConfig) {
            return $cachedConfig;
        }

        $config = $this->moduleData();

        if (! $isDevMode && $cache) {
            $cache->set($cacheKey, $config);
        }

        return $config;
    }

}
