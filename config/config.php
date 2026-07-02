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
        // Prod-safe default: enable explicitly with APP_DEBUG=true in local .env.
        'debug' => Env::bool('APP_DEBUG', false),
    ],
    'session' => [
        // Secure cookie flag: explicit SESSION_SECURE wins, otherwise inferred from APP_URL scheme.
        'secure' => Env::get('SESSION_SECURE') !== null
            ? Env::bool('SESSION_SECURE')
            : str_starts_with((string) Env::get('APP_URL', 'http://localhost'), 'https://'),
        // Idle timeout for authenticated sessions, seconds (default 8 hours).
        'idle_timeout' => (int) Env::get('SESSION_IDLE_TIMEOUT', '28800'),
    ],
    'auth' => [
        // Login rate limiting: max failures per email (and per IP) within the window.
        'max_attempts_email' => (int) Env::get('LOGIN_MAX_ATTEMPTS', '5'),
        'max_attempts_ip'    => (int) Env::get('LOGIN_MAX_ATTEMPTS_IP', '20'),
        'window_minutes'     => (int) Env::get('LOGIN_WINDOW_MINUTES', '15'),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'name' => Env::get('DB_NAME', 'gestionale_muratori'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
    ],
    'storage' => [
        // Overridable so tests (and alternative mounts) can relocate uploads.
        'uploads_path' => Env::get('UPLOADS_PATH', dirname(__DIR__) . '/storage/uploads'),
    ],
    // Business rule: block reservations that would drive stock negative.
    // (§4.2 — configurable; default = block.)
    'inventory' => [
        'allow_negative_stock' => Env::bool('ALLOW_NEGATIVE_STOCK', false),
    ],
];
