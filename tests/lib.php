<?php
/**
 * Minimal dependency-free test toolkit: assertions + a cookie-aware HTTP
 * client (curl) used by the end-to-end simulation. Loaded by tests/run.php.
 */
declare(strict_types=1);

final class T
{
    public static int $passed = 0;
    public static int $failed = 0;
    /** @var array<int,string> */
    public static array $failures = [];
    private static string $section = '';

    public static function section(string $name): void
    {
        self::$section = $name;
        echo "\n== {$name} ==\n";
    }

    public static function ok(bool $condition, string $message): void
    {
        if ($condition) {
            self::$passed++;
            echo "  PASS  {$message}\n";
        } else {
            self::$failed++;
            $full = '[' . self::$section . '] ' . $message;
            self::$failures[] = $full;
            echo "  FAIL  {$message}\n";
        }
    }

    public static function equals(mixed $expected, mixed $actual, string $message): void
    {
        $cond = $expected == $actual;
        if (!$cond) {
            $message .= ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
        }
        self::ok($cond, $message);
    }

    public static function throws(callable $fn, string $message): void
    {
        try {
            $fn();
            self::ok(false, $message . ' (no exception thrown)');
        } catch (\Throwable $e) {
            self::ok(true, $message . ' → "' . mb_substr($e->getMessage(), 0, 60) . '"');
        }
    }

    public static function summary(): int
    {
        echo "\n----------------------------------------\n";
        echo 'Result: ' . self::$passed . ' passed, ' . self::$failed . " failed\n";
        foreach (self::$failures as $f) {
            echo "  FAILED: {$f}\n";
        }
        return self::$failed === 0 ? 0 : 1;
    }
}

/**
 * One instance = one browser session (own cookie jar + remembered CSRF token).
 */
final class HttpClient
{
    private \CurlHandle $curl;
    public string $csrf = '';

    public function __construct(private string $baseUrl)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_COOKIEFILE     => '',   // in-memory cookie jar for this handle
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HEADER         => true,
        ]);
    }

    /**
     * @param array<string,mixed> $opts form|multipart|headers|json(bool: send Accept+XHR)
     * @return array{status:int, headers:string, body:string, json:mixed}
     */
    public function request(string $method, string $path, array $opts = []): array
    {
        $headers = $opts['headers'] ?? [];
        if ($opts['json'] ?? true) {
            $headers[] = 'X-Requested-With: XMLHttpRequest';
        }
        if ($this->csrf !== '' && !($opts['no_csrf'] ?? false)) {
            $headers[] = 'X-CSRF-Token: ' . $this->csrf;
        }

        curl_setopt_array($this->curl, [
            CURLOPT_URL           => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER    => $headers,
            CURLOPT_POSTFIELDS    => null,
        ]);

        if (isset($opts['multipart'])) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $opts['multipart']);
        } elseif (isset($opts['form'])) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($opts['form']));
        } elseif ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, '');
        }

        $raw = curl_exec($this->curl);
        if ($raw === false) {
            return ['status' => 0, 'headers' => '', 'body' => 'CURL: ' . curl_error($this->curl), 'json' => null];
        }

        $status     = (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $rawHeaders = substr((string) $raw, 0, $headerSize);
        $body       = substr((string) $raw, $headerSize);

        return [
            'status'  => $status,
            'headers' => $rawHeaders,
            'body'    => $body,
            'json'    => json_decode($body, true),
        ];
    }

    public function get(string $path, array $opts = []): array
    {
        return $this->request('GET', $path, $opts);
    }

    public function post(string $path, array $form = [], array $opts = []): array
    {
        return $this->request('POST', $path, $opts + ['form' => $form]);
    }

    /** Loads /login, remembers the CSRF token, performs the login POST. */
    public function login(string $email, string $password): array
    {
        $page = $this->get('/login', ['json' => false]);
        if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $page['body'], $m)) {
            $this->csrf = $m[1];
        }
        return $this->post('/login', ['email' => $email, 'password' => $password]);
    }

    /** Current session cookies as a "k=v; k2=v2" header value (for raw curl handles). */
    public function cookieHeader(): string
    {
        $lines = curl_getinfo($this->curl, CURLINFO_COOKIELIST) ?: [];
        $pairs = [];
        foreach ((array) $lines as $line) {
            $parts = explode("\t", (string) $line);
            if (count($parts) >= 7) {
                $pairs[] = $parts[5] . '=' . $parts[6];
            }
        }
        return implode('; ', $pairs);
    }
}
