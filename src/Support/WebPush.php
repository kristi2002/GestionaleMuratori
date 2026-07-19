<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal, dependency-free Web Push (VAPID) sender — the notification twin of
 * App\Support\Mailer. Disabled by default: it ships without keys and turns on
 * only once a VAPID key pair exists (scripts/vapid-keygen.php) and VAPID_SUBJECT
 * is set, so the platform never depends on push being configured.
 *
 * Deliberately uses openssl only (ES256 over P-256), NOT ext-gmp, which is absent
 * on the target hosts — so no Composer web-push library. Sends are "contentless"
 * (RFC 8030 tickle, no encrypted payload): the service worker receives the push
 * and fetches the notification text from GET /push/pending. This avoids the RFC
 * 8291 payload-encryption crypto entirely while still delivering a real, titled
 * lock-screen notification.
 *
 * The pure crypto (signJwt / publicKeyFromPem / derToRaw) takes an explicit PEM so
 * it is unit-testable without any configuration or a live push service.
 */
final class WebPush
{
    /** Enabled only when a subject and a readable private key are configured. */
    public static function isEnabled(): bool
    {
        return (bool) Config::get('push.enabled', true)
            && extension_loaded('openssl')
            && trim((string) Config::get('push.subject', '')) !== ''
            && self::privateKeyPem() !== null;
    }

    /** Application server (public) key, base64url — handed to pushManager.subscribe(). */
    public static function publicKey(): string
    {
        $pem = self::privateKeyPem();
        return $pem === null ? '' : self::publicKeyFromPem($pem);
    }

    /**
     * POST a contentless push to one endpoint. Returns the HTTP status code, or 0
     * when disabled / a transport error occurred. 404 and 410 mean the subscription
     * is gone and the caller should prune it.
     */
    public static function sendTo(string $endpoint): int
    {
        if (!self::isEnabled()) {
            return 0;
        }
        try {
            $pem     = (string) self::privateKeyPem();
            $subject = (string) Config::get('push.subject', '');
            $jwt     = self::signJwt($pem, self::origin($endpoint), $subject, time() + 12 * 3600);
            $headers = [
                'Authorization: vapid t=' . $jwt . ', k=' . self::publicKeyFromPem($pem),
                'TTL: ' . (int) Config::get('push.ttl', 2419200), // up to 4 weeks
                'Content-Length: 0',
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => '',
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => (int) Config::get('push.timeout', 10),
            ]);
            curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $status;
        } catch (\Throwable $e) {
            error_log('[webpush] ' . $e->getMessage());
            return 0;
        }
    }

    // --- Pure crypto (no config; unit-testable) --------------------------------

    /** Sign a VAPID ES256 JWT for one audience (the push endpoint's origin). */
    public static function signJwt(string $pem, string $audience, string $subject, int $exp): string
    {
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('VAPID: invalid private key');
        }
        $header  = self::b64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $payload = self::b64url((string) json_encode(['aud' => $audience, 'exp' => $exp, 'sub' => $subject]));
        $input   = $header . '.' . $payload;

        $der = '';
        if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('VAPID: signing failed');
        }
        return $input . '.' . self::b64url(self::derToRaw($der));
    }

    /** Uncompressed EC point (0x04 || X || Y), base64url, from an EC private-key PEM. */
    public static function publicKeyFromPem(string $pem): string
    {
        $key = openssl_pkey_get_private($pem);
        if ($key === false) {
            throw new RuntimeException('VAPID: invalid private key');
        }
        $d = openssl_pkey_get_details($key);
        if (!isset($d['ec']['x'], $d['ec']['y'])) {
            throw new RuntimeException('VAPID: not an EC key');
        }
        $point = "\x04"
            . str_pad((string) $d['ec']['x'], 32, "\0", STR_PAD_LEFT)
            . str_pad((string) $d['ec']['y'], 32, "\0", STR_PAD_LEFT);
        return self::b64url($point);
    }

    /** Convert an ECDSA DER signature (SEQUENCE{INTEGER r, INTEGER s}) to raw R||S (64 bytes). */
    public static function derToRaw(string $der): string
    {
        $offset = 0;
        if (($der[$offset++] ?? '') !== "\x30") {
            throw new RuntimeException('VAPID: malformed DER (no SEQUENCE)');
        }
        $seqLen = ord($der[$offset++]);
        if ($seqLen & 0x80) {
            $offset += ($seqLen & 0x7f); // long-form length (not expected for P-256)
        }
        $r = self::derInt($der, $offset);
        $s = self::derInt($der, $offset);
        return str_pad($r, 32, "\0", STR_PAD_LEFT) . str_pad($s, 32, "\0", STR_PAD_LEFT);
    }

    public static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function derInt(string $der, int &$offset): string
    {
        if (($der[$offset++] ?? '') !== "\x02") {
            throw new RuntimeException('VAPID: malformed DER (no INTEGER)');
        }
        $len = ord($der[$offset++]);
        $val = substr($der, $offset, $len);
        $offset += $len;
        $val = ltrim($val, "\0");          // drop the DER sign-padding byte
        if (strlen($val) > 32) {
            $val = substr($val, -32);
        }
        return $val;
    }

    private static function origin(string $url): string
    {
        $p = parse_url($url);
        if (!isset($p['scheme'], $p['host'])) {
            throw new RuntimeException('VAPID: bad endpoint URL');
        }
        $origin = $p['scheme'] . '://' . $p['host'];
        return isset($p['port']) ? $origin . ':' . $p['port'] : $origin;
    }

    private static function privateKeyPem(): ?string
    {
        $path = (string) Config::get('push.private_key_path', '');
        if ($path === '' || !is_file($path)) {
            return null;
        }
        $pem = @file_get_contents($path);
        return $pem === false || $pem === '' ? null : $pem;
    }
}
