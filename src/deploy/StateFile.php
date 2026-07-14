<?php

namespace Noo\CraftModules\deploy;

use RuntimeException;

final class StateFile
{
    public function __construct(public readonly string $path) {}

    public function read(): ?string
    {
        if (! is_file($this->path)) {
            return null;
        }

        $value = trim((string) file_get_contents($this->path));

        return $value !== '' ? $value : null;
    }

    public function write(string $commit): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create deployment state directory: $directory");
        }

        $temporary = tempnam($directory, '.blitz-commit-');

        if ($temporary === false) {
            throw new RuntimeException("Unable to write deployment state: {$this->path}");
        }

        if (file_put_contents($temporary, $commit."\n", LOCK_EX) === false) {
            @unlink($temporary);
            throw new RuntimeException("Unable to write deployment state: {$this->path}");
        }

        if (! rename($temporary, $this->path)) {
            @unlink($temporary);
            throw new RuntimeException("Unable to replace deployment state: {$this->path}");
        }
    }
}
