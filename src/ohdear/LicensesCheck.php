<?php

namespace Noo\CraftModules\ohdear;

use Craft;
use craft\enums\LicenseKeyStatus;
use craft\helpers\App;
use OhDear\HealthCheckResults\CheckResult;
use webhubworks\ohdear\health\checks\Check;

class LicensesCheck extends Check
{
    private const CACHE_KEY = 'craft-modules.ohdear.licenses';

    protected array $ignore = [];
    protected bool $warnOnTrial = true;
    protected int $cacheDuration = 3600;

    public function ignore(array $handles): self
    {
        $this->ignore = $handles;
        return $this;
    }

    public function warnOnTrial(bool $warn = true): self
    {
        $this->warnOnTrial = $warn;
        return $this;
    }

    public function cacheFor(int $seconds): self
    {
        $this->cacheDuration = max(0, $seconds);
        return $this;
    }

    public function run(): CheckResult
    {
        $cache = Craft::$app->getCache();
        $licenseInfo = $cache->get(App::CACHE_KEY_LICENSE_INFO) ?: [];
        $fingerprint = md5(serialize([$this->ignore, $this->warnOnTrial, $licenseInfo]));
        $key = self::CACHE_KEY . ':' . $fingerprint;

        if ($this->cacheDuration > 0 && ($cached = $cache->get($key)) instanceof CheckResult) {
            return $cached;
        }

        $result = $this->evaluate($licenseInfo);

        if ($this->cacheDuration > 0) {
            $cache->set($key, $result, $this->cacheDuration);
        }

        return $result;
    }

    private function evaluate(array $licenseInfo): CheckResult
    {
        $failed = [];
        $warning = [];
        $ok = 0;

        if (!in_array('craft', $this->ignore, true)) {
            $status = $licenseInfo['craft']['status'] ?? LicenseKeyStatus::Unknown->value;
            $issues = $this->statusToIssues($status);

            [$bucket, $detail] = $this->classify($status, $issues, $status === LicenseKeyStatus::Trial->value);
            if ($bucket === 'failed') {
                $failed['craft'] = $detail;
            } elseif ($bucket === 'warning') {
                $warning['craft'] = $detail;
            } else {
                $ok++;
            }
        }

        foreach (Craft::$app->getPlugins()->getAllPluginInfo() as $handle => $info) {
            if (in_array($handle, $this->ignore, true)) {
                continue;
            }
            if (empty($info['licenseKey']) && empty($info['licenseKeyStatus'])) {
                continue;
            }

            $status = $info['licenseKeyStatus'] ?? LicenseKeyStatus::Unknown->value;
            $issues = $info['licenseIssues'] ?? [];
            $isTrial = !empty($info['isTrial']);

            [$bucket, $detail] = $this->classify($status, $issues, $isTrial);
            if ($bucket === 'failed') {
                $failed[$handle] = $detail;
            } elseif ($bucket === 'warning') {
                $warning[$handle] = $detail;
            } else {
                $ok++;
            }
        }

        $result = new CheckResult(
            name: 'Licenses',
            label: 'Craft & Plugin Licenses',
            shortSummary: sprintf('%d invalid, %d warning, %d ok', count($failed), count($warning), $ok),
            meta: ['failed' => $failed, 'warning' => $warning, 'okCount' => $ok],
        );

        if (!empty($failed)) {
            return $result->status(CheckResult::STATUS_FAILED)
                ->notificationMessage('Invalid licenses: ' . implode(', ', array_keys($failed)));
        }
        if (!empty($warning)) {
            return $result->status(CheckResult::STATUS_WARNING)
                ->notificationMessage('License issues: ' . implode(', ', array_keys($warning)));
        }
        return $result->status(CheckResult::STATUS_OK)
            ->notificationMessage('All licenses are valid.');
    }

    /** @return array{0: 'failed'|'warning'|'ok', 1: array} */
    private function classify(string $status, array $issues, bool $isTrial): array
    {
        $expired = [LicenseKeyStatus::Invalid->value, LicenseKeyStatus::Astray->value];

        if (in_array($status, $expired, true) || array_intersect($expired, $issues)) {
            return ['failed', $issues ?: [$status]];
        }
        if (!empty($issues)) {
            return ['warning', $issues];
        }
        if ($this->warnOnTrial && $isTrial) {
            return ['warning', ['trial']];
        }
        return ['ok', []];
    }

    private function statusToIssues(string $status): array
    {
        return match ($status) {
            LicenseKeyStatus::Invalid->value,
            LicenseKeyStatus::Mismatched->value,
            LicenseKeyStatus::Astray->value => [$status],
            default => [],
        };
    }
}
