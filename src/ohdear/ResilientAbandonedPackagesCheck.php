<?php

namespace Noo\CraftModules\ohdear;

use OhDear\HealthCheckResults\CheckResult;
use webhubworks\ohdear\health\checks\AbandonedPackagesCheck;
use webhubworks\ohdear\health\checks\RunsComposer;
use webhubworks\ohdear\health\exceptions\ComposerCommandFailed;

/**
 * Drop-in replacement for Check::abandonedPackages() that keeps the last
 * good cached result when `composer audit` fails transiently. Register in
 * config/ohdear.php as ResilientAbandonedPackagesCheck::new()->cachedViaCron(...).
 *
 * TODO: Check webhubworks/craft-ohdear for a fix accepting null replacement
 * values in AbandonedPackagesCheck::compute(). Once fixed, remove this compute
 * override and RunsComposer import; retain KeepsLastGoodResult.
 * Upstream: https://github.com/webhubworks/craft-ohdear/blob/master/src/health/checks/AbandonedPackagesCheck.php
 */
class ResilientAbandonedPackagesCheck extends AbandonedPackagesCheck
{
    use KeepsLastGoodResult;
    use RunsComposer {
        getAuditResult as protected;
        getWhyResult as protected;
    }

    protected function compute(): CheckResult
    {
        try {
            $auditResult = $this->getAuditResult();
            $packages = [];

            // Composer returns null when an abandoned package has no replacement.
            // Use the package names; the upstream check incorrectly requires string values.
            foreach (array_keys($auditResult['abandoned'] ?? []) as $packageName) {
                $why = $this->getWhyResult($packageName);
                $packages[$packageName] = [
                    'requiredBy' => [
                        'packageName' => $why[0],
                        'installedVersion' => $why[1],
                    ],
                ];
            }
        } catch (ComposerCommandFailed $e) {
            return new CheckResult(
                name: $this->checkResultName(),
                label: $this->checkResultLabel(),
                notificationMessage: $e->getMessage(),
                shortSummary: 'Check could not run',
                status: CheckResult::STATUS_WARNING,
            );
        }

        $count = count($packages);

        return new CheckResult(
            name: $this->checkResultName(),
            label: $this->checkResultLabel(),
            notificationMessage: $count === 0
                ? 'No abandoned packages found.'
                : $count.' abandoned package'.($count === 1 ? '' : 's').' found!',
            shortSummary: $count === 0 ? 'All maintained' : 'Some abandoned',
            status: $count === 0 ? CheckResult::STATUS_OK : CheckResult::STATUS_FAILED,
            meta: $packages,
        );
    }
}
