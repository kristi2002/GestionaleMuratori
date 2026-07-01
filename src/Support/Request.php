<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Immutable-ish view over the incoming HTTP request. Merges JSON body, form
 * body and query string for input access.
 */
final class Request
{
    public string $method = 'GET';
    public string $path = '/';
    /** @var array<string,mixed> */
    private array $query = [];
    /** @var array<string,mixed> */
    private array $body = [];

    public static function fromGlobals(string $base): self
    {
        $r = new self();
        $r->method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        $uri  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $uri  = rawurldecode($uri);
        $base = rtrim($base, '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $r->path = '/' . ltrim($uri, '/');
        if ($r->path === '') {
            $r->path = '/';
        }

        $r->query = $_GET;
        $body     = $_POST;

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (stripos($contentType, 'application/json') !== false) {
            $raw     = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = array_merge($body, $decoded);
            }
        }
        $r->body = $body;

        return $r;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** True for AJAX / JSON clients — drives JSON vs HTML error responses. */
    public function wantsJson(): bool
    {
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }
        return stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
    }
}
