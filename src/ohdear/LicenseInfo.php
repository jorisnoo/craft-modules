<?php

namespace Noo\CraftModules\ohdear;

use craft\enums\LicenseKeyStatus;

final class LicenseInfo
{
    public static function shouldCheckPlugin(array $info): bool
    {
        $status = $info['licenseKeyStatus'] ?? LicenseKeyStatus::Unknown->value;

        // Craft also uses `unknown` for free plugins. With no key, issue, or
        // trial state, there is no plugin license for the health check to verify.
        return ! empty($info['licenseKey']) ||
            $status !== LicenseKeyStatus::Unknown->value ||
            ! empty($info['licenseIssues']) ||
            ! empty($info['isTrial']);
    }
}
