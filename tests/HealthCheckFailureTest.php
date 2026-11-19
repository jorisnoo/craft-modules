<?php

use craft\helpers\App;
use Noo\CraftModules\ohdear\LicensesCheck;
use Noo\CraftModules\ohdear\ResilientCveCheck;
use Noo\CraftModules\ohdear\ResilientQueueCheck;
use OhDear\HealthCheckResults\CheckResult;
use yii\caching\ArrayCache;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

beforeEach(function () {
    $this->previousApp = Craft::$app;
    $this->cache = new ArrayCache;
    Craft::$app = new class($this->cache)
    {
        public function __construct(private ArrayCache $cache) {}

        public function getCache(): ArrayCache
        {
            return $this->cache;
        }

        public function getPlugins(): self
        {
            return $this;
        }

        public function getAllPluginInfo(): array
        {
            return [];
        }
    };
});

afterEach(function () {
    Craft::$app = $this->previousApp;
});

it('distinguishes cold, invalid and valid license data without waiting for a stale cached result', function () {
    $check = LicensesCheck::new();
    expect($check->run()->status)->toBe(CheckResult::STATUS_WARNING);
    $this->cache->set(App::CACHE_KEY_LICENSE_INFO, ['craft' => ['status' => 'invalid']]);
    expect($check->run()->status)->toBe(CheckResult::STATUS_FAILED);
    $this->cache->set(App::CACHE_KEY_LICENSE_INFO, ['craft' => ['status' => 'valid']]);
    expect($check->run()->status)->toBe(CheckResult::STATUS_OK);
});

it('keeps one missing-heartbeat grace period until a new heartbeat arrives', function () {
    $check = ResilientQueueCheck::new()->graceMinutes(1);
    $marker = 'craft-modules.ohdear.queue-heartbeat-missing-since';
    expect($check->run()->status)->toBe(CheckResult::STATUS_OK);
    $values = (new ReflectionProperty(ArrayCache::class, '_cache'))->getValue($this->cache);
    expect($values[$this->cache->buildKey($marker)][1])->toBe(0);
    $this->cache->set($marker, time() - 86401);
    expect($check->run()->status)->toBe(CheckResult::STATUS_WARNING);
    $this->cache->set(ResilientQueueCheck::CACHE_KEY, time());
    expect($check->run()->status)->toBe(CheckResult::STATUS_OK)->and($this->cache->get($marker))->toBeFalse();
});

it('retries a failed compute and keeps the last good timestamp while logging a second failure', function () {
    $check = new class extends ResilientCveCheck
    {
        public array $results;

        protected function compute(): CheckResult
        {
            return array_shift($this->results);
        }
    };
    (new ReflectionProperty(ResilientCveCheck::class, 'retryDelaySeconds'))->setValue($check, 0);
    $failure = new CheckResult(name: 'CVEs', label: 'CVEs', shortSummary: 'Check could not run', notificationMessage: 'Composer unavailable', status: CheckResult::STATUS_WARNING);
    $ok = new CheckResult(name: 'CVEs', label: 'CVEs', shortSummary: 'Secure', status: CheckResult::STATUS_OK);
    $key = 'ohdear-check-result:SecurityVulnerabilities';
    $check->results = [$failure, $ok];
    expect($check->refresh()->status)->toBe(CheckResult::STATUS_OK);
    $this->cache->set($key, ['result' => $ok, 'computedAt' => 101]);
    $check->results = [$failure, $failure];
    expect($check->refresh()->status)->toBe(CheckResult::STATUS_OK)
        ->and($this->cache->get($key)['computedAt'])->toBe(101)
        ->and(array_column(Craft::getLogger()->messages, 0))->toContain('SecurityVulnerabilities could not run: Composer unavailable; keeping last good result.');
});
