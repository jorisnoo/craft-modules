<?php

use Noo\CraftModules\ohdear\ResilientAbandonedPackagesCheck;
use OhDear\HealthCheckResults\CheckResult;
use yii\caching\ArrayCache;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

class FixtureAbandonedPackagesCheck extends ResilientAbandonedPackagesCheck
{
    public array $abandoned = [];

    public int $auditCalls = 0;

    protected function getAuditResult(): array
    {
        $this->auditCalls++;

        return ['abandoned' => $this->abandoned];
    }

    protected function getWhyResult(string $packageName): array
    {
        return ['example/parent', '1.0.0', 'requires', $packageName, '(*)'];
    }
}

it('caches abandoned-package results with or without replacements', function (array $abandoned) {
    $previousApp = Craft::$app;
    Craft::$app = new class
    {
        private ArrayCache $cache;

        public function __construct()
        {
            $this->cache = new ArrayCache;
        }

        public function getCache(): ArrayCache
        {
            return $this->cache;
        }
    };

    try {
        $check = FixtureAbandonedPackagesCheck::new()->cachedViaCron(7200);
        $check->abandoned = $abandoned;

        expect($check->run()->shortSummary)->toBe('Not yet computed');

        $refreshed = $check->refresh();
        $cached = $check->run();

        expect($cached->status)->toBe($abandoned === [] ? CheckResult::STATUS_OK : CheckResult::STATUS_FAILED)
            ->and($cached->shortSummary)->toBe($abandoned === [] ? 'All maintained' : 'Some abandoned')
            ->and($cached->notificationMessage)->toBe($refreshed->notificationMessage)
            ->and(array_keys($cached->meta))->toBe([...array_keys($abandoned), 'lastRefreshedAt'])
            ->and($check->auditCalls)->toBe(1);

        foreach (array_keys($abandoned) as $packageName) {
            expect($cached->meta[$packageName]['requiredBy'])->toBe([
                'packageName' => 'example/parent',
                'installedVersion' => '1.0.0',
            ]);
        }
    } finally {
        Craft::$app = $previousApp;
    }
})->with([
    'all maintained' => [[]],
    'no replacement' => [['example/abandoned' => null]],
    'with replacement' => [['example/abandoned' => 'example/replacement']],
    'mixed replacements' => [['example/abandoned' => null, 'example/another' => 'example/replacement']],
]);
