<?php

namespace Noo\CraftModules\deploy;

use RuntimeException;

final class StateFile
{
    public function __construct(public readonly string $path) {}

    /** @return array{commit: string, manifestSha256?: ?string}|null A legacy commit has no manifest key. */
    public function read(): ?array
    {
        if (! self::filePresent($this->path)) {
            return null;
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read deployment state: {$this->path}");
        }

        $value = trim($contents);
        if (self::isCommit($value)) {
            return ['commit' => $value];
        }

        $record = json_decode($value, true);
        if (! is_array($record) || ! self::isCommit($record['commit'] ?? null) || ! array_key_exists('manifestSha256', $record)
            || ($record['manifestSha256'] !== null && (! is_string($record['manifestSha256']) || ! preg_match('/\A[0-9a-f]{64}\z/', $record['manifestSha256'])))) {
            throw new RuntimeException("Invalid deployment state: {$this->path}");
        }

        return $record;
    }

    public static function manifestDigest(string $root): ?string
    {
        $path = $root.'/'.FrontendBuildCache::DEFAULT_MANIFEST;
        if (! self::filePresent($path)) {
            return null;
        }

        $digest = hash_file('sha256', $path);
        if ($digest === false) {
            throw new RuntimeException("Unable to hash Vite manifest: $path");
        }

        return $digest;
    }

    private static function isCommit(mixed $value): bool
    {
        return is_string($value) && preg_match('/\A(?:[0-9a-f]{40}|[0-9a-f]{64})\z/', $value) === 1;
    }

    private static function filePresent(string $path): bool
    {
        if (is_file($path)) {
            return true;
        }

        // A broken link, directory in place of a file, or inaccessible parent
        // is an error, not evidence that this deployment has no manifest/state.
        $ancestor = $path;
        while (! file_exists($ancestor) && ! is_link($ancestor)) {
            $parent = dirname($ancestor);
            if ($parent === $ancestor) {
                break;
            }
            $ancestor = $parent;
        }
        if ($ancestor === $path || ! is_dir($ancestor) || (PHP_OS_FAMILY !== 'Windows' && ! is_executable($ancestor))) {
            throw new RuntimeException("Unable to inspect deployment file: $path");
        }

        return false;
    }

    public function write(string $commit, ?string $manifestSha256): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create deployment state directory: $directory");
        }

        $contents = json_encode(['commit' => $commit, 'manifestSha256' => $manifestSha256], JSON_THROW_ON_ERROR)."\n";
        $temporary = tempnam($directory, '.blitz-commit-');

        if ($temporary === false) {
            throw new RuntimeException("Unable to write deployment state: {$this->path}");
        }

        try {
            if (realpath(dirname($temporary)) !== realpath($directory) || file_put_contents($temporary, $contents) !== strlen($contents)) {
                throw new RuntimeException("Unable to write deployment state: {$this->path}");
            }
            if (! rename($temporary, $this->path)) {
                throw new RuntimeException("Unable to replace deployment state: {$this->path}");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
