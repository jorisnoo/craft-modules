<?php

namespace Noo\CraftModules\deploy;

final readonly class DeployCachePlan
{
    /**
     * @param  string[]  $changedFiles
     * @param  string[]  $cacheKeys
     */
    public function __construct(
        public array $changedFiles,
        public array $cacheKeys,
        public bool $refreshBlitz,
        public bool $clearAll = false,
        public ?string $reason = null,
    ) {}

    public function hasWork(): bool
    {
        return $this->clearAll || $this->cacheKeys !== [] || $this->refreshBlitz;
    }
}
