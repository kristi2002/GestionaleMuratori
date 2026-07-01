<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Output helpers. All JSON responses follow the spec contract:
 *   { ok: bool, data?: mixed, error?: string }
 */
final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function ok(mixed $data = null): void
    {
        self::json(['ok' => true, 'data' => $data]);
    }

    public static function fail(string $error, int $status = 400): void
    {
        self::json(['ok' => false, 'error' => $error], $status);
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
    }

    public static function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }
}
