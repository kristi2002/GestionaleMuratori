<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Structured, greppable error logging + optional out-of-band alerting.
 *
 * Uncaught exceptions used to be written as a single free-text error_log line
 * and nothing else, so a production 500 was effectively invisible (e.g. the
 * 2026-07-11 PDF temp-dir incident — every PDF endpoint 500'd and no one was
 * notified). Logger emits one JSON line per event to the PHP error log
 * (container stderr in production, per deploy/php.ini) and, when
 * ALERT_WEBHOOK_URL is configured, best-effort POSTs a compact alert so a human
 * is told. Each request gets a short correlation id that is both logged and
 * shown to the user on the error page, so a report ("I saw error 3f9a…") maps
 * straight to a log line.
 *
 * Every path here is guarded: logging/alerting must never throw and never mask
 * the original error.
 */
final class Logger
{
    /** Correlation id for the current request, echoed to the user on error pages. */
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            try {
                self::$requestId = bin2hex(random_bytes(6));
            } catch (Throwable $e) {
                self::$requestId = substr(md5((string) mt_rand()), 0, 12);
            }
        }
        return self::$requestId;
    }

    /**
     * Record an uncaught exception as one structured line and fire an alert.
     *
     * @param array<string,mixed> $context extra fields (method, path, user_id, …)
     */
    public static function exception(Throwable $e, array $context = []): void
    {
        $record = [
            'ts'         => date('c'),
            'level'      => 'error',
            'request_id' => self::requestId(),
            'type'       => get_class($e),
            'message'    => $e->getMessage(),
            'file'       => $e->getFile() . ':' . $e->getLine(),
        ] + $context;

        self::write($record + ['trace' => $e->getTraceAsString()]);
        self::alert($record);
    }

    /** @param array<string,mixed> $record */
    private static function write(array $record): void
    {
        $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Prefix so app events are trivially greppable in a noisy shared log.
        error_log('gm ' . ($json !== false ? $json : ('log-encode-failed type=' . ($record['type'] ?? '?'))));
    }

    /**
     * Best-effort notification; a no-op unless ALERT_WEBHOOK_URL is set. Sends a
     * compact JSON `{text: …}` payload (Slack/Discord/Teams-compatible) with a
     * short timeout, throttled per error signature so one recurring fault can't
     * flood the channel.
     *
     * @param array<string,mixed> $record
     */
    private static function alert(array $record): void
    {
        try {
            $url = trim((string) Config::get('alerts.webhook_url', ''));
            if ($url === '' || !function_exists('curl_init')) {
                return;
            }
            if (!self::throttleOk((string) ($record['type'] ?? '') . '|' . (string) ($record['file'] ?? ''))) {
                return;
            }

            $app  = (string) Config::get('app.name', 'Gestionale');
            $where = isset($record['path'])
                ? ' [' . ($record['method'] ?? '?') . ' ' . $record['path'] . ']'
                : '';
            $text = "[{$app}] 500 " . ($record['type'] ?? 'Error') . ': '
                  . ($record['message'] ?? '') . $where
                  . ' (' . ($record['file'] ?? '') . ') ref=' . ($record['request_id'] ?? '');

            $payload = json_encode(['text' => $text], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_RETURNTRANSFER => true,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Throwable $e) {
            error_log('gm alert-failed: ' . $e->getMessage());
        }
    }

    /** File-based per-signature throttle. Returns true when it's OK to send. */
    private static function throttleOk(string $signature): bool
    {
        try {
            $window = (int) Config::get('alerts.min_interval', 300);
            if ($window <= 0) {
                return true;
            }
            $dir = dirname((string) Config::get('storage.pdf_temp_path', '')) . '/alerts';
            if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return true; // can't throttle → don't suppress the alert
            }
            $file = $dir . '/' . md5($signature) . '.ts';
            $last = is_file($file) ? (int) @file_get_contents($file) : 0;
            if (time() - $last < $window) {
                return false;
            }
            @file_put_contents($file, (string) time(), LOCK_EX);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }
}
