<?php

use Noo\CraftModules\log\FlareTarget;
use yii\log\Logger;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';

it('maps minimum Flare log levels to Yii levels', function () {
    expect(FlareTarget::levelsFor('warning'))->toBe(['error', 'warning'])
        ->and(FlareTarget::levelsFor('info'))->toBe(['error', 'warning', 'info'])
        ->and(FlareTarget::levelsFor('debug'))->toBe(['error', 'warning', 'info', 'trace'])
        ->and(FlareTarget::levelsFor('critical'))->toBe(['error']);
});

it('creates an OpenTelemetry logs payload', function () {
    $target = new FlareTarget([
        'serviceName' => 'Test Craft app',
        'serviceVersion' => '5.10.11',
        'serviceStage' => 'testing',
    ]);

    $payload = $target->makePayload([[
        'Something happened',
        Logger::LEVEL_WARNING,
        'tests',
        1_700_000_000.25,
        [['file' => '/app/example.php', 'line' => 42]],
        1_048_576,
    ]]);

    $resource = $payload['resourceLogs'][0];
    $record = $resource['scopeLogs'][0]['logRecords'][0];

    expect($resource['resource']['attributes'])->toContain([
        'key' => 'service.name',
        'value' => ['stringValue' => 'Test Craft app'],
    ])->and($record['severityText'])->toBe('warning')
        ->and($record['severityNumber'])->toBe(13)
        ->and($record['body'])->toBe(['stringValue' => 'Something happened'])
        ->and(abs($record['timeUnixNano'] - 1_700_000_000_250_000_000))->toBeLessThan(1_000);
});

it('retains actionable errors and warnings but not routine HTTP and translation noise', function () {
    $target = new FlareTarget(['levels' => FlareTarget::levelsFor('warning'), 'exportInterval' => 0]);
    $target->collect([
        ['bad request', Logger::LEVEL_ERROR, 'yii\web\HttpException:400', 0],
        ['forbidden', Logger::LEVEL_ERROR, 'yii\web\HttpException:403', 0],
        ['not found', Logger::LEVEL_ERROR, 'yii\web\HttpException:404', 0],
        ['missing translation', Logger::LEVEL_WARNING, 'yii\i18n\PhpMessageSource:app', 0],
        ['info', Logger::LEVEL_INFO, 'application', 0],
        ['service unavailable', Logger::LEVEL_ERROR, 'yii\web\HttpException:503', 0],
        ['warning', Logger::LEVEL_WARNING, 'application', 0],
    ], false);

    expect(array_column($target->messages, 0))->toBe(['service unavailable', 'warning']);
});
