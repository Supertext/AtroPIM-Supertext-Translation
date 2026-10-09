<?php

declare(strict_types=1);

// PHPStan needs AtroCore's classes: load the autoloader of an AtroPIM installation.
// CI sets ATRO_VENDOR (see .github/workflows/ci.yml → phpstan); locally, point it at
// the vendor/ folder of any AtroCore 2.4 / AtroPIM 1.16 installation.
$vendor = getenv('ATRO_VENDOR') ?: dirname(__DIR__) . '/vendor';
if (!is_file($vendor . '/autoload.php')) {
    fwrite(STDERR, "PHPStan: set ATRO_VENDOR to the vendor/ folder of an AtroPIM installation.\n");
    exit(1);
}
require $vendor . '/autoload.php';
