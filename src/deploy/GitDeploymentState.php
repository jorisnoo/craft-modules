<?php

namespace Noo\CraftModules\deploy;

use RuntimeException;
use Symfony\Component\Process\Process;

final class GitDeploymentState
{
    public function __construct(private readonly string $workingDirectory) {}

    public static function isCommitHash(string $revision): bool
    {
        return preg_match('/^[0-9a-f]{7,40}$/i', $revision) === 1;
    }

    public function currentCommit(string $revision = 'HEAD'): string
    {
        return trim($this->git(['rev-parse', '--verify', $this->revision($revision).'^{commit}']));
    }

    public function commitExists(string $revision): bool
    {
        if (! self::isCommitHash($revision)) {
            return false;
        }

        $process = $this->process(['cat-file', '-e', $revision.'^{commit}']);
        $process->run();

        return $process->isSuccessful();
    }

    public function fetchCommit(string $revision, string $remote = 'origin'): bool
    {
        if (! self::isCommitHash($revision)) {
            return false;
        }

        if ($this->commitExists($revision)) {
            return true;
        }

        $process = $this->process([
            'fetch',
            '--quiet',
            '--no-tags',
            '--depth=1',
            $this->revision($remote),
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
        $output = $this->git(['diff', '--name-only', '-z', $this->revision($from), $this->revision($to), '--']);

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(explode("\0", $output), fn (string $file) => $file !== ''));
    }

    /**
     * Rejects revisions git could parse as options (e.g. a crafted --from or
     * state file value such as "--output=...").
     */
    private function revision(string $revision): string
    {
        if ($revision === '' || str_starts_with($revision, '-') || preg_match('/[\s\x00-\x1f]/', $revision)) {
            throw new RuntimeException("Invalid Git revision: $revision");
        }

        return $revision;
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
