<?php

namespace Noo\CraftModules\ohdear;

use webhubworks\ohdear\health\checks\AbandonedPackagesCheck;

/**
 * Drop-in replacement for Check::abandonedPackages() that keeps the last
 * good cached result when `composer audit` fails transiently. Register in
 * config/ohdear.php as ResilientAbandonedPackagesCheck::new()->cachedViaCron(...).
 */
class ResilientAbandonedPackagesCheck extends AbandonedPackagesCheck
{
    use KeepsLastGoodResult;
}
