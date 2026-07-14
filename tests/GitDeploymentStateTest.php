<?php

use craft\helpers\FileHelper;
use Noo\CraftModules\deploy\GitDeploymentState;
use Symfony\Component\Process\Process;

afterEach(function () {
    if (! isset($this->repository) || ! is_dir($this->repository)) {
        return;
    }

    FileHelper::removeDirectory($this->repository);
});

it('compares the current tree with a previous commit', function () {
    $this->repository = sys_get_temp_dir().'/craft-modules-git-'.bin2hex(random_bytes(6));
    mkdir($this->repository);

    $git = fn (array $arguments) => (new Process(
        ['git', ...$arguments],
        $this->repository,
        ['GIT_AUTHOR_NAME' => 'Test', 'GIT_AUTHOR_EMAIL' => 'test@example.com', 'GIT_COMMITTER_NAME' => 'Test', 'GIT_COMMITTER_EMAIL' => 'test@example.com'],
    ))->mustRun();

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
