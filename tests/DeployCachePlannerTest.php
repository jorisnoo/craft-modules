<?php

use Noo\CraftModules\deploy\DeployCachePlanner;

it('plans template and frontend cache work', function () {
    $plan = (new DeployCachePlanner)->plan([
        'templates/_pages/home.twig',
        'src/js/app.js',
    ]);

    expect($plan->clearAll)->toBeFalse()
        ->and($plan->refreshBlitz)->toBeTrue()
        ->and($plan->cacheKeys)->toBe(['compiled-templates', 'vite-file-cache']);
});

it('plans broad application cache work', function () {
    $plan = (new DeployCachePlanner)->plan([
        'modules/SiteModule.php',
        'composer.lock',
    ]);

    expect($plan->refreshBlitz)->toBeTrue()
        ->and($plan->cacheKeys)->toBe([
            'compiled-classes',
            'cp-resources',
            'data',
        ]);
});

it('does nothing for unrelated deployment files', function () {
    $plan = (new DeployCachePlanner)->plan([
        'README.md',
        'deploy.sh',
    ]);

    expect($plan->hasWork())->toBeFalse();
});

it('creates a conservative full plan', function () {
    $plan = (new DeployCachePlanner)->full('No state exists.');

    expect($plan->clearAll)->toBeTrue()
        ->and($plan->refreshBlitz)->toBeTrue()
        ->and($plan->reason)->toBe('No state exists.');
});

it('refreshes HTML for each frontend input family', function (string $path) {
    $plan = (new DeployCachePlanner)->plan([$path]);

    expect($plan->cacheKeys)->toContain('vite-file-cache')
        ->and($plan->refreshBlitz)->toBeTrue();
})->with([
    'src/js/app.js', 'templates/base.twig', 'vite.config.ts', 'postcss.config.cjs',
    'tailwind.config.mjs', 'webpack.config.js', 'tsconfig.build.json', 'jsconfig.json',
    'package.json', 'package-lock.json', 'npm-shrinkwrap.json', 'yarn.lock', 'pnpm-lock.yaml',
    '.npmrc', '.env', '.env.production', 'config/vite.php',
]);
