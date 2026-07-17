<?php

use craft\helpers\FileHelper;
use Noo\CraftModules\deploy\GitDeploymentState;
use Symfony\Component\Process\Process;

/**
 * Runs git isolated from the user's global/system config, so signing
 * prompts, commit templates, or hooks can never stall the suite.
 */
function runGit(array $arguments, ?string $cwd = null): Process
{
    $process = new Process(['git', ...$arguments], $cwd, [
        'GIT_CONFIG_GLOBAL' => '/dev/null',
        'GIT_CONFIG_NOSYSTEM' => '1',
        'GIT_AUTHOR_NAME' => 'Test',
        'GIT_AUTHOR_EMAIL' => 'test@example.com',
        'GIT_COMMITTER_NAME' => 'Test',
        'GIT_COMMITTER_EMAIL' => 'test@example.com',
    ]);

    return $process->setTimeout(120)->mustRun();
}

afterEach(function () {
    if (! isset($this->repository) || ! is_dir($this->repository)) {
        return;
    }

    FileHelper::removeDirectory($this->repository);
});

it('compares the current tree with a previous commit', function () {
    $this->repository = sys_get_temp_dir().'/craft-modules-git-'.bin2hex(random_bytes(6));
    mkdir($this->repository);

    $git = fn (array $arguments) => runGit($arguments, $this->repository);

    $git(['init', '--quiet']);
    file_put_contents($this->repository.'/README.md', "First\n");
    $git(['add', 'README.md']);
    $git(['commit', '--quiet', '-m', 'First']);

    $state = new GitDeploymentState($this->repository);
    $firstCommit = $state->currentCommit();

    mkdir($this->repository.'/templates');
    file_put_contents($this->repository.'/templates/page.twig', "Second\n");
    $git(['add', 'templates/page.twig']);
    $git(['commit', '--quiet', '-m', 'Second']);

    expect($state->commitExists($firstCommit))->toBeTrue()
        ->and($state->changedFiles($firstCommit, 'HEAD'))->toBe(['templates/page.twig']);
});

it('fetches a missing deployment commit from origin', function () {
    $this->repository = sys_get_temp_dir().'/craft-modules-git-'.bin2hex(random_bytes(6));
    $origin = $this->repository.'-origin';
    $source = $this->repository.'-source';

    try {
        runGit(['init', '--quiet', '--bare', $origin]);
        runGit(['clone', '--quiet', $origin, $source]);

        $git = fn (array $arguments) => runGit($arguments, $source);

        file_put_contents($source.'/first.txt', "First\n");
        $git(['add', 'first.txt']);
        $git(['commit', '--quiet', '-m', 'First']);
        $firstCommit = trim($git(['rev-parse', 'HEAD'])->getOutput());
        $git(['push', '--quiet', 'origin', 'HEAD:main']);

        file_put_contents($source.'/second.txt', "Second\n");
        $git(['add', 'second.txt']);
        $git(['commit', '--quiet', '-m', 'Second']);
        $git(['push', '--quiet', 'origin', 'HEAD:main']);

        runGit(['clone', '--quiet', '--depth=1', '--branch=main', 'file://'.$origin, $this->repository]);
        $state = new GitDeploymentState($this->repository);

        expect($state->commitExists($firstCommit))->toBeFalse()
            ->and($state->fetchCommit($firstCommit))->toBeTrue()
            ->and($state->changedFiles($firstCommit, 'HEAD'))->toBe(['second.txt']);
    } finally {
        FileHelper::removeDirectory($origin);
        FileHelper::removeDirectory($source);
    }
});
