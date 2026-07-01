<?php
declare(strict_types=1);

use App\Support\Env;

// Ensure env is loaded even if config is required before bootstrap finishes.
Env::load(dirname(__DIR__) . '/.env');

return [
    'app' => [
        'name'  => Env::get('APP_NAME', 'Gestionale Muratori'),
        'env'   => Env::get('APP_ENV', 'local'),
        'url'   => Env::get('APP_URL', 'http://localhost'),
        'debug' => Env::bool('APP_DEBUG', true),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'name' => Env::get('DB_NAME', 'gestionale_muratori'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
    ],
    'storage' => [
        'uploads_path' => dirname(__DIR__) . '/storage/uploads',
    ],
    // Business rule: block reservations that would drive stock negative.
    // (§4.2 — configurable; default = block.)
    'inventory' => [
        'allow_negative_stock' => Env::bool('ALLOW_NEGATIVE_STOCK', false),
    ],
];
