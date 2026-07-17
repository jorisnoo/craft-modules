<?php

use craft\enums\LicenseKeyStatus;
use Noo\CraftModules\ohdear\LicenseInfo;

it('skips free plugins with no license information', function () {
    expect(LicenseInfo::shouldCheckPlugin([
        'licenseKey' => null,
        'licenseKeyStatus' => LicenseKeyStatus::Unknown->value,
        'licenseIssues' => [],
        'isTrial' => false,
    ]))->toBeFalse();
});

it('checks plugins with a license key whose status is unknown', function () {
    expect(LicenseInfo::shouldCheckPlugin([
        'licenseKey' => 'test-key',
        'licenseKeyStatus' => LicenseKeyStatus::Unknown->value,
        'licenseIssues' => [],
        'isTrial' => false,
    ]))->toBeTrue();
});

it('checks plugins with a known status, issue, or trial state', function (array $info) {
    expect(LicenseInfo::shouldCheckPlugin($info))->toBeTrue();
})->with([
    'known status' => [[
        'licenseKey' => null,
        'licenseKeyStatus' => LicenseKeyStatus::Valid->value,
        'licenseIssues' => [],
        'isTrial' => false,
    ]],
    'license issue' => [[
        'licenseKey' => null,
        'licenseKeyStatus' => LicenseKeyStatus::Unknown->value,
        'licenseIssues' => ['required'],
        'isTrial' => false,
    ]],
    'trial state' => [[
        'licenseKey' => null,
        'licenseKeyStatus' => LicenseKeyStatus::Unknown->value,
        'licenseIssues' => [],
        'isTrial' => true,
    ]],
]);

it('normalizes hostnames and URLs', function (?string $input, ?string $expected) {
    expect(LicenseInfo::normalizeHost($input))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'blank' => ['   ', null],
    'bare host' => ['example.com', 'example.com'],
    'uppercase' => ['Example.COM', 'example.com'],
    'www prefix' => ['www.example.com', 'example.com'],
    'trailing dot' => ['example.com.', 'example.com'],
    'full url' => ['https://www.example.com/some/path', 'example.com'],
    'url with port' => ['https://example.com:8443/', 'example.com'],
    'host with port' => ['example.com:8080', 'example.com'],
]);

it('knows which hosts a licensed domain covers', function (?string $domain, ?string $host, bool $covers) {
    expect(LicenseInfo::domainCovers($domain, $host))->toBe($covers);
})->with([
    'same domain' => ['example.com', 'example.com', true],
    'www variant' => ['example.com', 'www.example.com', true],
    'subdomain' => ['example.com', 'staging.example.com', true],
    'licensed domain as url' => ['https://example.com', 'example.com', true],
    'different domain' => ['example.com', 'other.com', false],
    'suffix but not subdomain' => ['example.com', 'notexample.com', false],
    'null domain' => [null, 'example.com', false],
    'null host' => ['example.com', null, false],
]);

it('dismisses a craft license mismatch reported through a non-canonical host', function () {
    // The CDN-origin scenario: the license belongs to the primary site's
    // domain, but the last phone-home went through the origin hostname.
    expect(LicenseInfo::isCraftMismatchGenuine(
        licensedDomain: 'sonicmatter.ch',
        licenseInfoHost: 'wild-pathos-flora.example.li',
        primaryHost: 'https://www.sonicmatter.ch/',
    ))->toBeFalse();
});

it('flags a craft license that belongs to a different domain', function () {
    expect(LicenseInfo::isCraftMismatchGenuine(
        licensedDomain: 'old-domain.com',
        licenseInfoHost: 'example.com',
        primaryHost: 'example.com',
    ))->toBeTrue();
});

it('falls back to the phone-home host when the licensed domain is unknown', function (?string $infoHost, bool $genuine) {
    expect(LicenseInfo::isCraftMismatchGenuine(null, $infoHost, 'example.com'))->toBe($genuine);
})->with([
    'reported from the primary host itself' => ['example.com', true],
    'reported from a subdomain of the primary host' => ['staging.example.com', true],
    'reported from a foreign host' => ['origin.other-host.net', false],
    'no host recorded (console phone-home)' => [null, false],
]);

it('treats a mismatch as genuine when the primary host is unknown', function () {
    expect(LicenseInfo::isCraftMismatchGenuine('example.com', 'example.com', null))->toBeTrue();
});
