<?php

namespace Noo\CraftModules\deploy;

use RuntimeException;
use Symfony\Component\Process\Process;

final class GitDeploymentState
{
    public function __construct(private readonly string $workingDirectory) {}

    public function currentCommit(string $revision = 'HEAD'): string
    {
        return trim($this->git(['rev-parse', '--verify', '--end-of-options', $revision.'^{commit}']));
    }

    public function commitExists(string $revision): bool
    {
        if ($revision === '' || str_starts_with($revision, '-')) {
            return false;
        }

        $process = $this->process(['cat-file', '-e', $revision.'^{commit}']);
        $process->run();

        return $process->isSuccessful();
    }

    public function fetchCommit(string $revision, string $remote = 'origin'): bool
    {
        if ($this->commitExists($revision)) {
            return true;
        }

        if (! preg_match('/\A[0-9a-f]{7,64}\z/i', $revision)) {
            return false;
        }

        $process = $this->process([
            'fetch',
            '--quiet',
            '--no-tags',
            '--depth=1',
            '--',
            $remote,
            $revision,
        ]);
        $process->setTimeout(30);
        $process->setEnv(['GIT_TERMINAL_PROMPT' => '0']);
        try {
            $process->run();
        } catch (RuntimeException) {
            return false;
        }

        return $process->isSuccessful() && $this->commitExists($revision);
    }

    /**
     * @return string[]
     */
    public function changedFiles(string $from, string $to): array
    {
        $output = $this->git(['diff', '--name-only', '-z', '--end-of-options', $from, $to, '--']);

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
