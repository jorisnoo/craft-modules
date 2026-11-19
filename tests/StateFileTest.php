<?php

use craft\helpers\FileHelper;
use Noo\CraftModules\deploy\StateFile;

afterEach(function () {
    if (isset($this->directory) && is_dir($this->directory)) {
        FileHelper::removeDirectory($this->directory);
    }
});

it('reads legacy and structured deployment state and atomically replaces both fields', function () {
    $this->directory = sys_get_temp_dir().'/craft-modules-'.bin2hex(random_bytes(6));
    $state = new StateFile($this->directory.'/state/commit');

    expect($state->read())->toBeNull();

    $first = str_repeat('1', 40);
    $second = str_repeat('2', 40);
    $digest = hash('sha256', 'built output');
    mkdir(dirname($state->path), 0777, true);
    file_put_contents($state->path, "$first\n");
    expect($state->read())->toBe(['commit' => $first]);
    $oldHandle = fopen($state->path, 'rb');
    try {
        $state->write($second, $digest);
        expect($state->read())->toBe(['commit' => $second, 'manifestSha256' => $digest])
            ->and(stream_get_contents($oldHandle))->toBe("$first\n");
    } finally {
        fclose($oldHandle);
    }
    $state->write($second, null);
    expect($state->read())->toBe(['commit' => $second, 'manifestSha256' => null]);
});

it('rejects corrupt state without mistaking it for a new deployment', function (string $record) {
    $this->directory = sys_get_temp_dir().'/craft-modules-'.bin2hex(random_bytes(6));
    mkdir($this->directory);
    $state = new StateFile($this->directory.'/commit');
    file_put_contents($state->path, $record);
    expect(fn () => $state->read())->toThrow(RuntimeException::class);
})->with(['', 'not a commit', '{"partial":', '{"commit":"bad","manifestSha256":null}',
    json_encode(['commit' => str_repeat('a', 40)]),
    json_encode(['commit' => str_repeat('a', 40), 'manifestSha256' => 'broken']),
]);
