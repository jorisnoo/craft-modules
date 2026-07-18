<?php

namespace Noo\CraftModules\ohdear;

use Craft;
use OhDear\HealthCheckResults\CheckResult;
use webhubworks\ohdear\health\checks\QueueCheck;

/**
 * Drop-in replacement for Check::queueHealth() that tolerates a missing
 * heartbeat for a grace period instead of warning immediately. The heartbeat
 * lives in Craft's data cache, so every deploy (or manual cache clear) wipes
 * it and upstream warns until the next cron-pushed QueueHealthJob is
 * processed — a window Oh Dear regularly notices. A genuinely dead queue
 * still alerts: the warning returns once the grace period expires, and a
 * stale (rather than missing) heartbeat is handled by upstream unchanged.
 */
class ResilientQueueCheck extends QueueCheck
{
    private const MISSING_SINCE_CACHE_KEY = 'craft-modules.ohdear.queue-heartbeat-missing-since';

    protected int $graceMinutes = 10;

    public function graceMinutes(int $minutes): self
    {
        if ($minutes < 1) {
            throw new \InvalidArgumentException('The minutes parameter must be greater than 0.');
        }

        $this->graceMinutes = $minutes;

        return $this;
    }

    public function run(): CheckResult
    {
        $cache = Craft::$app->getCache();

        if ($cache->get(self::CACHE_KEY) !== false) {
            $cache->delete(self::MISSING_SINCE_CACHE_KEY);

            return parent::run();
        }

        $missingSince = $cache->get(self::MISSING_SINCE_CACHE_KEY);

        if ($missingSince === false) {
            $missingSince = time();
            // The marker shares the data cache with the heartbeat, so a cache
            // clear resets both and the grace period restarts from the first
            // check after the clear — the earliest anchor we can observe.
            $cache->set(self::MISSING_SINCE_CACHE_KEY, $missingSince, 86400);
        }

        if (time() - $missingSince > $this->graceMinutes * 60) {
            return parent::run();
        }

        return (new CheckResult(
            name: 'Queue',
            label: 'Queue Health',
        ))
            ->shortSummary('Awaiting heartbeat.')
            ->meta([
                'latestHeartbeat' => null,
                'missingSince' => date('Y-m-d H:i:se', (int) $missingSince),
                'graceMinutes' => $this->graceMinutes,
            ])
            ->notificationMessage("The queue heartbeat is missing, most likely wiped by a cache clear. This becomes a warning if no heartbeat returns within {$this->graceMinutes} minutes.")
            ->status(CheckResult::STATUS_OK);
    }
}
