<?php

namespace Noo\CraftModules\ohdear;

use Craft;
use OhDear\HealthCheckResults\CheckResult;

/**
 * Overrides CachesResult::refresh() so a transient composer failure — e.g. a
 * Packagist timeout during `composer audit` — keeps the last good cached
 * result instead of overwriting it with a warning that Oh Dear turns into a
 * notification. The failed compute is retried once first; if it still fails,
 * the previous entry's TTL is renewed but its computedAt is kept, so
 * staleness is still measured from the last successful compute and a
 * persistent failure surfaces as a stale warning via run() after
 * staleAfterSeconds.
 *
 * Couples to three upstream implementation details of webhubworks/craft-ohdear
 * (all verified against 5.7.2): the cache key format and entry shape in
 * CachesResult, and the 'Check could not run' shortSummary both
 * composer-backed checks use for a ComposerCommandFailed. If upstream changes
 * the wording, refresh() degrades to upstream behavior (failure results are
 * cached again); if the key or shape changes, run() reports "not yet
 * computed" — neither failure mode can mask a real vulnerability.
 */
trait KeepsLastGoodResult
{
    /**
     * Mirrors CachesResult::$staleAfterSeconds, which is private to the
     * upstream trait; recorded here so refresh() can compute the same TTL.
     */
    private ?int $mirroredStaleAfterSeconds = null;

    /**
     * `composer audit` is the only composer call in these checks that hits
     * the network; Packagist blips are usually shorter than this.
     */
    private int $retryDelaySeconds = 5;

    public function cachedViaCron(int $staleAfterSeconds): static
    {
        $this->mirroredStaleAfterSeconds = $staleAfterSeconds;

        return parent::cachedViaCron($staleAfterSeconds);
    }

    public function refresh(): CheckResult
    {
        $result = $this->compute();

        if ($this->couldNotRun($result)) {
            sleep($this->retryDelaySeconds);
            $result = $this->compute();
        }

        if ($this->couldNotRun($result)) {
            $cached = Craft::$app->getCache()->get($this->cacheKey());

            if (is_array($cached) && ($cached['result'] ?? null) instanceof CheckResult) {
                Craft::$app->getCache()->set($this->cacheKey(), $cached, $this->cacheTtl());

                return $cached['result'];
            }
        }

        Craft::$app->getCache()->set(
            $this->cacheKey(),
            ['result' => $result, 'computedAt' => time()],
            $this->cacheTtl(),
        );

        return $result;
    }

    /**
     * CveCheck and AbandonedPackagesCheck both convert a ComposerCommandFailed
     * into a warning result with this shortSummary inside compute(), so the
     * result content is the only failure signal that reaches refresh().
     */
    private function couldNotRun(CheckResult $result): bool
    {
        return $result->shortSummary === 'Check could not run';
    }

    /** Must match the private CachesResult::getCacheKey(). */
    private function cacheKey(): string
    {
        return 'ohdear-check-result:'.$this->checkResultName();
    }

    /** Must match the TTL used in CachesResult::refresh(). */
    private function cacheTtl(): int
    {
        return $this->mirroredStaleAfterSeconds !== null ? $this->mirroredStaleAfterSeconds * 2 : 86400;
    }
}
