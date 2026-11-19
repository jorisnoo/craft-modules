<?php

use craft\base\Plugin;
use craft\console\Application;
use craft\helpers\FileHelper;
use craft\services\Plugins;
use Noo\CraftModules\console\controllers\DeployController;
use Noo\CraftModules\deploy\FrontendBuildCache;
use Noo\CraftModules\deploy\StateFile;
use yii\base\Component;
use yii\console\ExitCode;

require_once dirname(__DIR__).'/vendor/yiisoft/yii2/Yii.php';
require_once dirname(__DIR__).'/vendor/craftcms/cms/src/Craft.php';

beforeEach(function () {
    $this->originalRoot = Craft::getAlias('@root', false);
    $this->originalApp = Craft::$app;
    $this->root = sys_get_temp_dir().'/craft-modules-deploy-'.bin2hex(random_bytes(6));
    mkdir($this->root);
    Craft::setAlias('@root', $this->root);
    runGit(['init', '--quiet'], $this->root);
    runGit(['commit', '--quiet', '--allow-empty', '-m', 'Initial'], $this->root);
    $this->sha = trim(runGit(['rev-parse', 'HEAD'], $this->root)->getOutput());
    $this->manifest = $this->root.'/'.FrontendBuildCache::DEFAULT_MANIFEST;
    $this->state = new StateFile($this->root.'/last-success');
    $this->calls = [];
    $this->failCache = false;
    $this->blitz = new class
    {
        public bool $queue;

        public bool $interactive;

        public int $status = ExitCode::OK;

        public function runAction(string $id): int
        {
            expect($id)->toBe('refresh');

            return $this->status;
        }
    };
    $plugin = (new ReflectionClass(Plugin::class))->newInstanceWithoutConstructor();
    $plugin->set('cachePurger', new class extends Component
    {
        public const EVENT_BEFORE_PURGE_ALL_CACHE = 'beforePurge';
    });
    $plugins = $this->createStub(Plugins::class);
    $plugins->method('getPlugin')->willReturn($plugin);
    Craft::$app = $this->getMockBuilder(Application::class)->disableOriginalConstructor()->onlyMethods(['createController'])->getMock();
    Craft::$app->set('plugins', $plugins);
    Craft::$app->method('createController')->willReturnCallback(function ($route) {
        $this->calls[] = $route;

        return [$this->blitz, 'refresh'];
    });
    $this->deploy = $this->getMockBuilder(DeployController::class)->disableOriginalConstructor()->onlyMethods(['cacheOptions', 'stdout', 'stderr'])->getMock();
    $options = [];
    foreach (['vite-file-cache', 'compiled-templates'] as $key) {
        $options[$key] = ['action' => function () use ($key) {
            $this->calls[] = $key;

            return ! $this->failCache;
        }];
    }
    $this->deploy->method('cacheOptions')->willReturn($options);
    $this->deploy->stateFile = $this->state->path;
});

afterEach(function () {
    Craft::$app = $this->originalApp;
    Craft::setAlias('@root', $this->originalRoot ?: null);
    FileHelper::removeDirectory($this->root);
});

it('refreshes once per built manifest transition even at the same Git commit', function () {
    file_put_contents($this->state->path, $this->sha."\n");
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)
        ->and($this->calls)->toBe(['vite-file-cache', 'blitz/cache/refresh'])
        ->and($this->state->read())->toBe(['commit' => $this->sha, 'manifestSha256' => null]);
    $this->calls = [];
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe([]);

    mkdir(dirname($this->manifest), 0777, true);
    foreach (['old.css', 'new.css'] as $filename) {
        $contents = json_encode(['main' => ['file' => $filename]]);
        file_put_contents($this->manifest, $contents);
        expect($this->deploy->actionIndex())->toBe(ExitCode::OK)
            ->and($this->calls)->toBe(['vite-file-cache', 'blitz/cache/refresh'])
            ->and($this->state->read())->toBe(['commit' => $this->sha, 'manifestSha256' => hash('sha256', $contents)]);
        $this->calls = [];
        expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe([]);
    }
    unlink($this->manifest);
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)
        ->and($this->calls)->toBe(['vite-file-cache', 'blitz/cache/refresh'])
        ->and($this->state->read()['manifestSha256'])->toBeNull();
    $this->calls = [];
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe([]);
});

it('preserves last success on dry-run, a failed cache clear, and a failed Blitz refresh', function () {
    $this->state->write($this->sha, null);
    $baseline = file_get_contents($this->state->path);
    mkdir(dirname($this->manifest), 0777, true);
    file_put_contents($this->manifest, '{}');
    $this->deploy->dryRun = true;
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe([])
        ->and(file_get_contents($this->state->path))->toBe($baseline);
    $this->deploy->dryRun = false;
    $this->failCache = true;
    expect($this->deploy->actionIndex())->toBe(ExitCode::UNSPECIFIED_ERROR)->and($this->calls)->toBe(['vite-file-cache'])
        ->and(file_get_contents($this->state->path))->toBe($baseline);
    $this->calls = [];
    $this->failCache = false;
    $this->blitz->status = ExitCode::UNSPECIFIED_ERROR;
    expect($this->deploy->actionIndex())->toBe(ExitCode::UNSPECIFIED_ERROR)->and($this->calls)->toBe(['vite-file-cache', 'blitz/cache/refresh'])
        ->and(file_get_contents($this->state->path))->toBe($baseline);
    $this->blitz->status = ExitCode::OK;
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->state->read()['manifestSha256'])->toBe(hash('sha256', '{}'));
});

it('does not treat unreadable manifest paths as removal', function () {
    $this->state->write($this->sha, hash('sha256', 'old'));
    $baseline = file_get_contents($this->state->path);
    mkdir(dirname($this->manifest), 0777, true);
    symlink('missing-target', $this->manifest);
    expect($this->deploy->actionIndex())->toBe(ExitCode::UNSPECIFIED_ERROR)->and($this->calls)->toBe([])
        ->and(file_get_contents($this->state->path))->toBe($baseline);
});

it('honors explicit Git baselines and force without discarding the artifact comparison', function () {
    $this->state->write($this->sha, null);
    mkdir($this->root.'/templates');
    file_put_contents($this->root.'/templates/page.twig', 'hello');
    runGit(['add', 'templates/page.twig'], $this->root);
    runGit(['commit', '--quiet', '-m', 'Change template'], $this->root);
    $this->deploy->from = 'HEAD';
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe([]);
    mkdir(dirname($this->manifest), 0777, true);
    file_put_contents($this->manifest, '{}');
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe(['vite-file-cache', 'blitz/cache/refresh']);
    $this->calls = [];
    $this->deploy->from = $this->sha;
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe(['compiled-templates', 'vite-file-cache', 'blitz/cache/refresh']);
    $this->calls = [];
    $this->deploy->from = '';
    $this->deploy->force = true;
    expect($this->deploy->actionIndex())->toBe(ExitCode::OK)->and($this->calls)->toBe(['vite-file-cache', 'compiled-templates', 'blitz/cache/refresh']);
});
