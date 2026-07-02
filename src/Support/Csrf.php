<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Session-bound CSRF token. Enforced centrally in the front controller for
 * every POST request; the client sends it back via the X-CSRF-Token header
 * (set globally in app.js) or a `_token` form field.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /** Current session token, generated on first use. */
    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    /** Constant-time comparison against the session token. */
    public static function check(?string $candidate): bool
    {
        if ($candidate === null || $candidate === '') {
            return false;
        }
        return hash_equals(self::token(), $candidate);
    }

    /** Token supplied by the current request (header first, then body field). */
    public static function fromRequest(Request $request): ?string
    {
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($header) && $header !== '') {
            return $header;
        }
        $field = $request->input('_token');
        return is_string($field) && $field !== '' ? $field : null;
    }
}
