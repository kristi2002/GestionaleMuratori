<?php
/**
 * Application bootstrap.
 * Prefers Composer's autoloader when present, otherwise falls back to the
 * bundled PSR-4 autoloader. Loads environment variables.
 */
declare(strict_types=1);

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

// Always register the bundled autoloader as a fallback for App\ classes.
require __DIR__ . '/autoload.php';

\App\Support\Env::load(dirname(__DIR__) . '/.env');
