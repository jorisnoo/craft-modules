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
