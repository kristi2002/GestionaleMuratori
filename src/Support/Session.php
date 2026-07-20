<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Thin wrapper around PHP sessions with flash-message support.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (PHP_SAPI !== 'cli') {
            ini_set('session.use_strict_mode', '1');
            // DB-backed sessions survive container restarts (redeploys) — file
            // sessions live in the container and are wiped on each deploy. Set
            // SESSION_DRIVER=files to fall back to PHP's default handler.
            if (Config::get('session.driver', 'database') === 'database') {
                session_set_save_handler(new DatabaseSessionHandler(), true);
            }
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => (bool) Config::get('session.secure', false),
                'path'     => '/',
            ]);
            session_start();
        }
    }

    /** True when the authenticated session has been idle longer than $maxIdleSeconds. */
    public static function idleExpired(int $maxIdleSeconds): bool
    {
        $last = self::get('_last_activity');
        return is_int($last) && (time() - $last) > $maxIdleSeconds;
    }

    /** Record activity for the idle-timeout check. */
    public static function touch(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['_last_activity'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Set a flash value when $value is provided; otherwise read-and-clear it. */
    public static function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }
        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }
}
