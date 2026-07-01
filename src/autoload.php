<?php
/**
 * Minimal PSR-4 autoloader for the "App\" namespace -> src/.
 * Used when Composer's vendor/autoload.php is not present.
 */
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
