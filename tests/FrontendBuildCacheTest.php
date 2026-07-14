<?php

use craft\helpers\FileHelper;
use Noo\CraftModules\deploy\FrontendBuildCache;

beforeEach(function () {
    $this->root = sys_get_temp_dir().'/craft-modules-build-'.bin2hex(random_bytes(6));
    $this->cacheDirectory = sys_get_temp_dir().'/craft-modules-build-cache-'.bin2hex(random_bytes(6));
    $this->sharedDirectory = sys_get_temp_dir().'/craft-modules-build-shared-'.bin2hex(random_bytes(6));
    mkdir($this->root.'/src', 0777, true);
    mkdir($this->root.'/templates', 0777, true);
    file_put_contents($this->root.'/package.json', '{"scripts":{"build":"vite build"}}');
    file_put_contents($this->root.'/package-lock.json', '{"lockfileVersion":3}');
    file_put_contents($this->root.'/src/app.js', 'console.log("first")');
    file_put_contents($this->root.'/templates/page.twig', '{{ first }}');
});

afterEach(function () {
    FileHelper::removeDirectory($this->root);
    FileHelper::removeDirectory($this->cacheDirectory);
    FileHelper::removeDirectory($this->sharedDirectory);
});

it('fingerprints the contents of symlinked files and directories', function () {
    mkdir($this->sharedDirectory, 0777, true);
    file_put_contents($this->sharedDirectory.'/shared.js', 'first');
    symlink($this->sharedDirectory.'/shared.js', $this->root.'/src/shared.js');

    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $fileFingerprint = $cache->fingerprint(['src']);
    file_put_contents($this->sharedDirectory.'/shared.js', 'second');

    expect($cache->fingerprint(['src']))->not->toBe($fileFingerprint);

    mkdir($this->sharedDirectory.'/components');
    file_put_contents($this->sharedDirectory.'/components/card.js', 'first');
    symlink($this->sharedDirectory.'/components', $this->root.'/src/components');
    $directoryFingerprint = $cache->fingerprint(['src']);
    file_put_contents($this->sharedDirectory.'/components/card.js', 'second');

    expect($cache->fingerprint(['src']))->not->toBe($directoryFingerprint);
});

it('fingerprints package, source, and template contents deterministically', function () {
    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $inputs = ['package.json', 'package-lock.json', 'src', 'templates'];
    $first = $cache->fingerprint($inputs);

    expect($cache->fingerprint(array_reverse($inputs)))->toBe($first);

    file_put_contents($this->root.'/templates/page.twig', '{{ second }}');

    expect($cache->fingerprint($inputs))->not->toBe($first);
});

it('includes standard Vite environment files in build inputs', function () {
    file_put_contents($this->root.'/.env', 'VITE_API_URL=first');
    file_put_contents($this->root.'/.env.production', 'VITE_API_URL=production');
    file_put_contents($this->root.'/.env.staging.local', 'VITE_API_URL=staging');

    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $inputs = $cache->defaultInputs();

    expect($inputs)->toContain('.env', '.env.production', '.env.staging.local');

    $fingerprint = $cache->fingerprint($inputs);
    file_put_contents($this->root.'/.env.production', 'VITE_API_URL=changed');

    expect($cache->fingerprint($inputs))->not->toBe($fingerprint);
});

it('stores and restores a matching build with its manifest', function () {
    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $fingerprint = $cache->fingerprint(['package.json', 'package-lock.json', 'src', 'templates']);
    mkdir($this->root.'/web/dist/.vite', 0777, true);
    file_put_contents($this->root.'/web/dist/.vite/manifest.json', '{}');
    file_put_contents($this->root.'/web/dist/app.js', 'built');

    $cache->store($fingerprint);
    FileHelper::removeDirectory($this->root.'/web/dist');

    expect($cache->has($fingerprint))->toBeTrue()
        ->and($cache->restore($fingerprint))->toBeTrue()
        ->and(file_get_contents($this->root.'/web/dist/app.js'))->toBe('built')
        ->and(is_file($this->root.'/web/dist/.vite/manifest.json'))->toBeTrue();
});

it('replaces an existing cache entry when requested', function () {
    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $fingerprint = $cache->fingerprint(['src']);
    mkdir($this->root.'/web/dist/.vite', 0777, true);
    file_put_contents($this->root.'/web/dist/.vite/manifest.json', '{}');
    file_put_contents($this->root.'/web/dist/app.js', 'old build');
    $cache->store($fingerprint);

    file_put_contents($this->root.'/web/dist/app.js', 'forced build');
    $cache->store($fingerprint, true);
    FileHelper::removeDirectory($this->root.'/web/dist');
    $cache->restore($fingerprint);

    expect(file_get_contents($this->root.'/web/dist/app.js'))->toBe('forced build');
});

it('rejects a build without a Vite manifest', function () {
    $cache = new FrontendBuildCache($this->root, $this->cacheDirectory);
    $fingerprint = $cache->fingerprint(['src']);
    mkdir($this->root.'/web/dist', 0777, true);

    $cache->store($fingerprint);
})->throws(RuntimeException::class, 'Vite manifest missing after build.');
