<?php

namespace Noo\CraftModules\log;

use Craft;
use Throwable;
use yii\helpers\VarDumper;
use yii\log\Logger;
use yii\log\Target;

/**
 * Sends Craft/Yii log messages to Flare's OpenTelemetry logs endpoint.
 *
 * This can be replaced with FlareLogHandler once the Craft Flare plugin
 * supports spatie/flare-client-php v3.
 */
class FlareTarget extends Target
{
    public string $apiKey = '';

    public string $baseUrl = 'https://ingress.flareapp.io';

    public string $serviceName = 'CraftCMS';

    public ?string $serviceVersion = null;

    public ?string $serviceStage = null;

    public int $timeout = 2;

    public $logVars = [];

    /**
     * @return string[]
     */
    public static function levelsFor(string $minimalLevel): array
    {
        return match (strtolower($minimalLevel)) {
            'debug', 'trace' => ['error', 'warning', 'info', 'trace'],
            'info', 'notice' => ['error', 'warning', 'info'],
            'error', 'critical', 'alert', 'emergency' => ['error'],
            default => ['error', 'warning'],
        };
    }

    public function export(): void
    {
        if ($this->apiKey === '' || $this->messages === []) {
            return;
        }

        $curl = curl_init();

        if ($curl === false) {
            return;
        }

        $endpoint = rtrim($this->baseUrl, '/').'/v1/logs?'.http_build_query([
            'key' => $this->apiKey,
        ]);

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-token: '.$this->apiKey,
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($this->makePayload($this->messages), JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 1,
        ]);

        try {
            curl_exec($curl);
        } catch (Throwable) {
            // Logging must never break the request or recursively create logs.
        } finally {
            curl_close($curl);
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $messages
     */
    public function makePayload(array $messages): array
    {
        $serviceVersion = $this->serviceVersion;

        if ($serviceVersion === null && Craft::$app !== null) {
            $serviceVersion = Craft::$app->getVersion();
        }

        return [
            'resourceLogs' => [[
                'resource' => [
                    'attributes' => $this->attributesToOpenTelemetry(array_filter([
                        'service.name' => $this->serviceName,
                        'service.version' => $serviceVersion,
                        'service.stage' => $this->serviceStage,
                        'telemetry.sdk.language' => 'php',
                        'telemetry.sdk.name' => 'jorisnoo/craft-modules',
                        'host.name' => php_uname('n'),
                    ], fn (mixed $value) => $value !== null)),
                    'droppedAttributesCount' => 0,
                ],
                'scopeLogs' => [[
                    'scope' => [
                        'name' => 'craft/yii',
                        'version' => $serviceVersion ?? 'unknown',
                        'attributes' => [],
                        'droppedAttributesCount' => 0,
                    ],
                    'logRecords' => array_map(
                        fn (array $message) => $this->makeLogRecord($message),
                        $messages,
                    ),
                ]],
            ]],
        ];
    }

    /**
     * @param  array<int, mixed>  $message
     */
    private function makeLogRecord(array $message): array
    {
        [$text, $level, $category, $timestamp] = $message;
        [$severityText, $severityNumber] = $this->severity($level);

        if (! is_string($text)) {
            $text = $text instanceof Throwable
                ? (string) $text
                : VarDumper::export($text);
        }

        $context = array_filter([
            'category' => $category ?: null,
            'trace' => $message[4] ?? null,
            'memory' => $message[5] ?? null,
        ], fn (mixed $value) => $value !== null);

        return [
            'timeUnixNano' => (int) round($timestamp * 1_000_000_000),
            'observedTimeUnixNano' => (int) round(microtime(true) * 1_000_000_000),
            'severityText' => $severityText,
            'severityNumber' => $severityNumber,
            'body' => $this->valueToOpenTelemetry($text),
            'attributes' => $this->attributesToOpenTelemetry([
                'log.context' => $context,
            ]),
        ];
    }

    /**
     * @return array{string, int}
     */
    private function severity(int|string $level): array
    {
        return match ($level) {
            Logger::LEVEL_ERROR, 'error' => ['error', 17],
            Logger::LEVEL_WARNING, 'warning' => ['warning', 13],
            Logger::LEVEL_INFO, 'info' => ['info', 9],
            default => ['debug', 5],
        };
    }

    private function attributesToOpenTelemetry(array $attributes): array
    {
        return array_values(array_filter(array_map(function (mixed $value, string $key) {
            $mapped = $this->valueToOpenTelemetry($value);

            return $mapped === null ? null : [
                'key' => $key,
                'value' => $mapped,
            ];
        }, $attributes, array_keys($attributes))));
    }

    private function valueToOpenTelemetry(mixed $value): ?array
    {
        return match (true) {
            is_string($value) => ['stringValue' => $value],
            is_bool($value) => ['boolValue' => $value],
            is_int($value) => ['intValue' => $value],
            is_float($value) => ['doubleValue' => $value],
            is_array($value) && array_is_list($value) => [
                'arrayValue' => [
                    'values' => array_values(array_filter(array_map(
                        fn (mixed $item) => $this->valueToOpenTelemetry($item),
                        $value,
                    ))),
                ],
            ],
            is_array($value) => [
                'kvlistValue' => [
                    'values' => $this->attributesToOpenTelemetry($value),
                ],
            ],
            $value === null => null,
            default => ['stringValue' => json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE)],
        };
    }
}
