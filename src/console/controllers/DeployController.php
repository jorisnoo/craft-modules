<?php

namespace Noo\CraftModules\console\controllers;

use Craft;
use craft\console\Controller;
use craft\helpers\Console;
use craft\helpers\Queue;
use craft\utilities\ClearCaches;
use Noo\CraftModules\deploy\DeployCachePlan;
use Noo\CraftModules\deploy\DeployCachePlanner;
use Noo\CraftModules\deploy\FrontendBuildCache;
use Noo\CraftModules\deploy\GitDeploymentState;
use Noo\CraftModules\deploy\StateFile;
use Symfony\Component\Process\Process;
use Throwable;
use webhubworks\ohdear\health\jobs\QueueHealthJob;
use yii\console\ExitCode;

class DeployController extends Controller
{
    public bool $queue = true;

    public bool $force = false;

    public bool $dryRun = false;

    public ?string $stateFile = null;

    public string $from = '';

    public string $to = 'HEAD';

    public ?string $buildCacheDir = null;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), [
            'queue',
            'force',
            'dryRun',
            'stateFile',
            'from',
            'to',
            'buildCacheDir',
        ]);
    }

    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), [
            'n' => 'dryRun',
        ]);
    }

    public function getHelpSummary(): string
    {
        return 'Manages deployment caches, including reusable frontend builds and affected Craft caches.';
    }

    public function actionIndex(): int
    {
        $root = Craft::getAlias('@root');
        $git = new GitDeploymentState($root);
        $state = new StateFile($this->resolveStateFile());
        $planner = new DeployCachePlanner;

        try {
            $currentCommit = $git->currentCommit($this->to);
            $previousCommit = $this->from !== '' ? $this->from : $state->read();

            if ($this->force) {
                $plan = $planner->full('A full refresh was forced.');
            } elseif ($previousCommit === null) {
                $plan = $planner->full('No previous successful deployment was recorded.');
            } elseif (! $git->fetchCommit($previousCommit)) {
                $plan = $planner->full("The previous commit $previousCommit is unavailable in this clone.");
            } else {
                $plan = $planner->plan($git->changedFiles($previousCommit, $currentCommit));
            }

            $this->printPlan($plan, $previousCommit, $currentCommit, $state->path);

            if (! $this->dryRun) {
                $this->executePlan($plan);
                $state->write($currentCommit);
                $this->stdout("Deployment cache state updated to $currentCommit.\n", Console::FG_GREEN);
            }
        } catch (Throwable $exception) {
            $this->stderr($exception->getMessage()."\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    public function actionBuild(): int
    {
        $root = Craft::getAlias('@root');
        $cacheDirectory = $this->resolveBuildCacheDirectory();
        $cache = new FrontendBuildCache($root, $cacheDirectory);

        try {
            $fingerprint = $cache->fingerprint($cache->defaultInputs());
            $this->stdout("Frontend build fingerprint: $fingerprint\n");
            $this->stdout("Build cache: $cacheDirectory\n");

            if (! $this->force && $cache->has($fingerprint)) {
                if ($this->dryRun) {
                    $this->stdout("Matching frontend build found; it would be restored.\n", Console::FG_GREEN);

                    return ExitCode::OK;
                }

                $cache->restore($fingerprint);
                $this->stdout("Restored matching frontend build from cache.\n", Console::FG_GREEN);

                return ExitCode::OK;
            }

            if ($this->dryRun) {
                $this->stdout("No matching frontend build found; npm ci and npm run build would run.\n", Console::FG_YELLOW);

                return ExitCode::OK;
            }

            $this->runProcess(['npm', 'ci'], $root);
            $this->runProcess(['npm', 'run', 'build'], $root);
            $cache->store($fingerprint, $this->force);
            $cache->prune();
            $this->stdout("Frontend build completed and cached.\n", Console::FG_GREEN);
        } catch (Throwable $exception) {
            $this->stderr($exception->getMessage()."\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    private function executePlan(DeployCachePlan $plan): void
    {
        if ($plan->clearAll) {
            $this->runConsoleAction('clear-caches/all');
        } else {
            $availableCaches = array_column(ClearCaches::cacheOptions(), null, 'key');

            foreach ($plan->cacheKeys as $cacheKey) {
                if (! isset($availableCaches[$cacheKey])) {
                    $this->stdout("Skipping unavailable cache: $cacheKey\n", Console::FG_YELLOW);

                    continue;
                }

                $this->runConsoleAction("clear-caches/$cacheKey");
            }
        }

        if (
            ($plan->clearAll || in_array('data', $plan->cacheKeys, true)) &&
            class_exists(QueueHealthJob::class)
        ) {
            // Clearing the data cache wipes the Oh Dear queue heartbeat; push a
            // replacement so a healthy worker restores it without waiting for cron.
            Queue::push(new QueueHealthJob);
            $this->stdout("Queued an Oh Dear queue heartbeat to replace the cleared one.\n");
        }

        if ($plan->refreshBlitz) {
            if (Craft::$app->getPlugins()->getPlugin('blitz') === null) {
                $this->stdout("Blitz is not installed or enabled; skipping its refresh.\n", Console::FG_YELLOW);
            } else {
                $this->runConsoleAction('blitz/cache/refresh', ['queue' => $this->queue]);
            }
        }
    }

    private function runConsoleAction(string $route, array $options = []): void
    {
        $result = Craft::$app->createController($route);

        if ($result === false) {
            throw new \RuntimeException("Console action not found: $route");
        }

        [$controller, $actionId] = $result;
        $controller->interactive = $this->interactive;

        foreach ($options as $name => $value) {
            $controller->{$name} = $value;
        }

        $exitCode = $controller->runAction($actionId);

        if ($exitCode !== null && $exitCode !== ExitCode::OK) {
            throw new \RuntimeException("Console action failed with exit code $exitCode: $route");
        }
    }

    private function runProcess(array $command, string $workingDirectory): void
    {
        $this->stdout('$ '.implode(' ', $command)."\n", Console::BOLD);
        $process = new Process($command, $workingDirectory);
        $process->setTimeout(null);
        $exitCode = $process->run(function (string $type, string $output): void {
            $type === Process::ERR ? $this->stderr($output) : $this->stdout($output);
        });

        if ($exitCode !== ExitCode::OK) {
            throw new \RuntimeException(sprintf('%s failed with exit code %d.', implode(' ', $command), $exitCode));
        }
    }

    private function printPlan(DeployCachePlan $plan, ?string $previousCommit, string $currentCommit, string $statePath): void
    {
        $this->stdout('Deployment cache plan'.($this->dryRun ? ' (dry run)' : '').":\n", Console::BOLD);
        $this->stdout('  From: '.($previousCommit ?? '(none)')."\n");
        $this->stdout("  To:   $currentCommit\n");
        $this->stdout("  State: $statePath\n");

        if ($plan->reason !== null) {
            $this->stdout("  {$plan->reason}\n", Console::FG_YELLOW);
        }

        if ($plan->changedFiles !== []) {
            $this->stdout("  Changed files:\n");
            foreach ($plan->changedFiles as $file) {
                $this->stdout("    $file\n");
            }
        }

        if (! $plan->hasWork()) {
            $this->stdout("  No cache work is required.\n", Console::FG_GREEN);

            return;
        }

        if ($plan->clearAll) {
            $this->stdout("  Clear all registered Craft caches.\n");
        } elseif ($plan->cacheKeys !== []) {
            $this->stdout('  Clear: '.implode(', ', $plan->cacheKeys)."\n");
        }

        if ($plan->refreshBlitz) {
            $this->stdout('  Refresh Blitz'.($this->queue ? ' in the queue' : ' immediately').".\n");
        }
    }

    private function resolveStateFile(): string
    {
        if ($this->stateFile !== null && $this->stateFile !== '') {
            return Craft::getAlias($this->stateFile);
        }

        $forgeSiteRoot = getenv('FORGE_SITE_ROOT');

        if (is_string($forgeSiteRoot) && $forgeSiteRoot !== '') {
            return rtrim($forgeSiteRoot, '/').'/.deploy-state/blitz-commit';
        }

        return Craft::getAlias('@storage/runtime/blitz-deploy/commit');
    }

    private function resolveBuildCacheDirectory(): string
    {
        if ($this->buildCacheDir !== null && $this->buildCacheDir !== '') {
            return Craft::getAlias($this->buildCacheDir);
        }

        $forgeSiteRoot = getenv('FORGE_SITE_ROOT');

        if (is_string($forgeSiteRoot) && $forgeSiteRoot !== '') {
            return rtrim($forgeSiteRoot, '/').'/.deploy-cache/frontend-builds';
        }

        return Craft::getAlias('@storage/runtime/deploy-build-cache');
    }
}
