<?php

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
