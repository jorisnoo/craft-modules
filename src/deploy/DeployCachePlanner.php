<?php

namespace Noo\CraftModules\deploy;

final class DeployCachePlanner
{
    /**
     * @param  string[]  $changedFiles
     */
    public function plan(array $changedFiles): DeployCachePlan
    {
        $cacheKeys = [];
        $refreshBlitz = false;

        foreach ($changedFiles as $file) {
            $file = ltrim(str_replace('\\', '/', $file), '/');

            if ($this->isIn($file, ['templates/', 'translations/'])) {
                $cacheKeys[] = 'compiled-templates';
                $refreshBlitz = true;
            }

            if ($this->isFrontendFile($file)) {
                $cacheKeys[] = 'vite-file-cache';
                $refreshBlitz = true;
            }

            if ($this->isIn($file, ['modules/'])) {
                array_push($cacheKeys, 'compiled-classes', 'data', 'cp-resources');
                $refreshBlitz = true;
            }

            if ($this->isIn($file, ['config/', 'migrations/'])) {
                array_push($cacheKeys, 'data', 'compiled-templates');
                $refreshBlitz = true;
            }

            if (in_array($file, ['composer.json', 'composer.lock'], true)) {
                array_push($cacheKeys, 'compiled-classes', 'data', 'cp-resources');
                $refreshBlitz = true;
            }
        }

        $cacheKeys = array_values(array_unique($cacheKeys));
        sort($cacheKeys);

        return new DeployCachePlan(
            changedFiles: array_values(array_unique($changedFiles)),
            cacheKeys: $cacheKeys,
            refreshBlitz: $refreshBlitz,
        );
    }

    public function full(string $reason, array $changedFiles = []): DeployCachePlan
    {
        return new DeployCachePlan(
            changedFiles: array_values(array_unique($changedFiles)),
            cacheKeys: [],
            refreshBlitz: true,
            clearAll: true,
            reason: $reason,
        );
    }

    private function isFrontendFile(string $file): bool
    {
        if ($this->isIn($file, ['src/'])) {
            return true;
        }

        if (in_array($file, ['package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml'], true)) {
            return true;
        }

        return preg_match('/^(vite\.config\.|config\/vite\.php$)/', $file) === 1;
    }

    /**
     * @param  string[]  $prefixes
     */
    private function isIn(string $file, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($file, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
