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

// The bundled Supertext SDK (lib/supertext-sdk) isn't in that installation.
spl_autoload_register(static function (string $class): void {
    $prefix = 'SupertextTranslation\\Sdk\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/lib/supertext-sdk/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
