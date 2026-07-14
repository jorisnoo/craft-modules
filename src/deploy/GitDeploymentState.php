<?php

namespace Noo\CraftModules\deploy;

use RuntimeException;
use Symfony\Component\Process\Process;

final class GitDeploymentState
{
    public function __construct(private readonly string $workingDirectory) {}

    public function currentCommit(string $revision = 'HEAD'): string
    {
        return trim($this->git(['rev-parse', '--verify', $revision.'^{commit}']));
    }

    public function commitExists(string $revision): bool
    {
        $process = $this->process(['cat-file', '-e', $revision.'^{commit}']);
        $process->run();

        return $process->isSuccessful();
    }

    public function fetchCommit(string $revision, string $remote = 'origin'): bool
    {
        if ($this->commitExists($revision)) {
            return true;
        }

        $process = $this->process([
            'fetch',
            '--quiet',
            '--no-tags',
            '--depth=1',
            $remote,
            $revision,
        ]);
        $process->run();

        return $process->isSuccessful() && $this->commitExists($revision);
    }

    /**
     * @return string[]
     */
    public function changedFiles(string $from, string $to): array
    {
        $output = $this->git(['diff', '--name-only', '-z', $from, $to, '--']);

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(explode("\0", $output), fn (string $file) => $file !== ''));
    }

    private function git(array $arguments): string
    {
        $process = $this->process($arguments);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Git command failed.');
        }

        return $process->getOutput();
    }

    private function process(array $arguments): Process
    {
        return new Process(['git', ...$arguments], $this->workingDirectory);
    }
}
