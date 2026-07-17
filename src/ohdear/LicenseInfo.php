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

    /**
     * Normalizes a hostname or URL to a bare lowercase host, without a
     * leading `www.` or trailing dot. Returns null when no host can be found.
     */
    public static function normalizeHost(?string $host): ?string
    {
        if ($host === null) {
            return null;
        }

        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }

        if (! str_contains($host, '://')) {
            $host = 'http://'.$host;
        }

        $host = parse_url($host, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', rtrim($host, '.')) ?: null;
    }

    /**
     * Whether a Craft license registered to $domain covers $host.
     * A license covers its domain and any subdomain of it.
     */
    public static function domainCovers(?string $domain, ?string $host): bool
    {
        $domain = self::normalizeHost($domain);
        $host = self::normalizeHost($host);

        if ($domain === null || $host === null) {
            return false;
        }

        return $host === $domain || str_ends_with($host, '.'.$domain);
    }

    /**
     * Whether a cached `mismatched` Craft license status points at a real
     * problem, rather than at license info that was last refreshed while
     * phoning home through a non-canonical hostname (a CDN origin URL, a
     * staging alias, …). Craft's control panel dismisses those the same way,
     * see craft\helpers\App::licenseIssues().
     */
    public static function isCraftMismatchGenuine(?string $licensedDomain, ?string $licenseInfoHost, ?string $primaryHost): bool
    {
        $primaryHost = self::normalizeHost($primaryHost);
        if ($primaryHost === null) {
            // Nothing to compare against, so the mismatch cannot be dismissed.
            return true;
        }

        // When Craft cached which domain the license belongs to, that answers
        // the question directly: only a license that does not cover the
        // primary site is a real mismatch.
        if (self::normalizeHost($licensedDomain) !== null) {
            return ! self::domainCovers($licensedDomain, $primaryHost);
        }

        // Otherwise only trust a mismatch that was reported while phoning
        // home from the primary site's own domain.
        return self::domainCovers($primaryHost, $licenseInfoHost);
    }
}
