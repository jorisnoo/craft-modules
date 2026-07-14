<?php

use Noo\CraftModules\deploy\StateFile;

afterEach(function () {
    if (isset($this->directory) && is_dir($this->directory)) {
        array_map('unlink', glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }
});

it('reads and atomically replaces deployment state', function () {
    $this->directory = sys_get_temp_dir().'/craft-modules-'.bin2hex(random_bytes(6));
    $state = new StateFile($this->directory.'/state/commit');

    expect($state->read())->toBeNull();

    $state->write('first');
    expect($state->read())->toBe('first');

    $state->write('second');
    expect($state->read())->toBe('second');

    unlink($this->directory.'/state/commit');
    rmdir($this->directory.'/state');
});
