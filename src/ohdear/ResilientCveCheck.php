<?php

namespace Noo\CraftModules\ohdear;

use webhubworks\ohdear\health\checks\CveCheck;

/**
 * Drop-in replacement for Check::cve() that keeps the last good cached
 * result when `composer audit` fails transiently. Register in
 * config/ohdear.php as ResilientCveCheck::new()->cachedViaCron(...).
 */
class ResilientCveCheck extends CveCheck
{
    use KeepsLastGoodResult;
}
