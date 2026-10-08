<?php

// The unit tests cover app/Api and app/Translation/Planner.php, which have no AtroCore
// dependencies: they run with only PHPUnit available (no AtroCore installation needed).
if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}

spl_autoload_register(static function (string $class): void {
    foreach (['SupertextTranslation\\Tests\\' => __DIR__ . '/', 'SupertextTranslation\\' => __DIR__ . '/../app/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
});
