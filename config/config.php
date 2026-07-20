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
        // Backing driver for uploaded files. 'local' today; an 's3' driver slots
        // into App\Support\Storage\Storage::disk() (ADR-0001 Phase 1) without any
        // call-site change — needed before the app can run more than one replica.
        'driver'       => Env::get('STORAGE_DRIVER', 'local'),
        // Overridable so tests (and alternative mounts) can relocate uploads.
        'uploads_path' => Env::get('UPLOADS_PATH', dirname(__DIR__) . '/storage/uploads'),
        // mPDF's scratch space (font cache + image temp). Must be writable by the
        // web-server user: the container runs as www-data and only owns storage/,
        // while mPDF's own default (vendor/mpdf/mpdf/tmp) is root-owned there.
        'pdf_temp_path' => Env::get('PDF_TEMP_PATH', dirname(__DIR__) . '/storage/tmp/mpdf'),
    ],
    // Error alerting: when a webhook URL is set, uncaught 500s best-effort POST a
    // compact {text: …} message (Slack/Discord/Teams-compatible). Off by default;
    // structured JSON logging to stderr happens regardless. See App\Support\Logger.
    'alerts' => [
        'webhook_url'  => Env::get('ALERT_WEBHOOK_URL', ''),
        // Per-error-signature throttle (seconds) so a recurring fault can't flood.
        'min_interval' => (int) Env::get('ALERT_MIN_INTERVAL', '300'),
    ],
    // Business rule: block reservations that would drive stock negative.
    // (§4.2 — configurable; default = block.)
    'inventory' => [
        'allow_negative_stock' => Env::bool('ALLOW_NEGATIVE_STOCK', false),
    ],
    // Giornale dei Lavori weather auto-fill via Open-Meteo (no API key needed).
    // Disabled in the test suite so daily-log creation never hits the network.
    'weather' => [
        'enabled'  => Env::bool('WEATHER_ENABLED', true),
        'endpoint' => Env::get('WEATHER_ENDPOINT', 'https://api.open-meteo.com/v1/forecast'),
        'timeout'  => (int) Env::get('WEATHER_TIMEOUT', '5'),
    ],
    // Contractor identity printed on invoice / quote / S.A.L. PDFs (the header
    // partial reads these). Empty by default; set the COMPANY_* env vars.
    'company' => [
        'name'    => Env::get('COMPANY_NAME', Env::get('APP_NAME', 'Gestionale Muratori')),
        'address' => Env::get('COMPANY_ADDRESS', ''),
        'vat'     => Env::get('COMPANY_VAT', ''),
        'phone'   => Env::get('COMPANY_PHONE', ''),
        'email'   => Env::get('COMPANY_EMAIL', ''),
    ],
    // Fatturazione elettronica (SdI). The XML is always generatable; signing and
    // transmission are OFF by default and require the firm's own certificate and
    // SdI-provider account. 'manual' transmitter = the admin downloads the (signed)
    // file and uploads it through their provider / the Agenzia delle Entrate portal.
    // See docs/CONFIGURATION.md for what to supply to go fully in-house.
    'einvoice' => [
        'enabled'     => Env::bool('EINVOICE_ENABLED', false),
        // CAdES signing: needs a qualified certificate (usually from the SdI provider
        // or a firma-digitale device). Provide PEM cert + private key.
        'sign'        => Env::bool('EINVOICE_SIGN', false),
        'cert_path'   => Env::get('EINVOICE_CERT_PATH', ''),
        'key_path'    => Env::get('EINVOICE_KEY_PATH', ''),
        'key_pass'    => Env::get('EINVOICE_KEY_PASS', ''),
        // Only 'manual' is wired today; API/PEC adapters plug in here (ADR-0009 revisit).
        'transmitter' => Env::get('EINVOICE_TRANSMITTER', 'manual'),
    ],
    // Scheduled automation (scripts/scheduler.php): how far ahead to alert on
    // expiring compliance documents.
    'scheduler' => [
        'compliance_days' => (int) Env::get('SCHEDULER_COMPLIANCE_DAYS', '30'),
    ],
    // E-mail (alert digests). DISABLED by default: the platform ships without an
    // SMTP account. Set MAIL_ENABLED=true + the MAIL_* vars to turn it on.
    'mail' => [
        'enabled'           => Env::bool('MAIL_ENABLED', false),
        'transport'         => Env::get('MAIL_TRANSPORT', 'smtp'),   // 'smtp' | 'mail'
        'host'              => Env::get('MAIL_HOST', ''),
        'port'              => (int) Env::get('MAIL_PORT', '587'),
        'username'          => Env::get('MAIL_USERNAME', ''),
        'password'          => Env::get('MAIL_PASSWORD', ''),
        'encryption'        => Env::get('MAIL_ENCRYPTION', 'tls'),    // 'tls' | 'ssl' | ''
        'from_address'      => Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
        'from_name'         => Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Gestionale Muratori')),
        'timeout'           => (int) Env::get('MAIL_TIMEOUT', '10'),
        // Comma-separated override; empty = every active admin's address.
        'digest_recipients' => Env::get('MAIL_DIGEST_RECIPIENTS', ''),
    ],
    // Web Push (VAPID). DISABLED until configured: generate a key pair with
    // `php scripts/vapid-keygen.php` (writes config/vapid_private.pem) and set
    // VAPID_SUBJECT (a mailto: or https: contact). PUSH_ENABLED=false force-disables.
    // Uses openssl only — no ext-gmp, no Composer web-push library.
    'push' => [
        'enabled'          => Env::bool('PUSH_ENABLED', true),
        'subject'          => Env::get('VAPID_SUBJECT', ''),
        'private_key_path' => Env::get('VAPID_PRIVATE_KEY_PATH', dirname(__DIR__) . '/config/vapid_private.pem'),
        'ttl'              => (int) Env::get('PUSH_TTL', '2419200'),
        'timeout'          => (int) Env::get('PUSH_TIMEOUT', '10'),
    ],
];
