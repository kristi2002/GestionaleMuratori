<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Minimal, dependency-free e-mail sender. Disabled by default (MAIL_ENABLED=false)
 * so the platform ships without an SMTP account; enable it and set the MAIL_* env
 * vars to turn on the scheduler's alert digests (see App\Services\SchedulerService).
 *
 * Two transports, no Composer dependency:
 *   - 'mail'  → PHP mail() (needs a configured MTA/sendmail on the host)
 *   - 'smtp'  → a compact SMTP client over fsockopen (STARTTLS or implicit SSL)
 *
 * Message construction (buildMimeMessage / encodeHeader) is pure and unit-tested;
 * actual delivery is a no-op until enabled.
 */
final class Mailer
{
    public static function isEnabled(): bool
    {
        return (bool) Config::get('mail.enabled', false);
    }

    /**
     * @param string|array<int,string> $to
     * @return bool true when handed to the transport, false when disabled/failed
     */
    public static function send($to, string $subject, string $htmlBody): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        $recipients = self::normalizeRecipients($to);
        if ($recipients === []) {
            return false;
        }

        try {
            return (string) Config::get('mail.transport', 'smtp') === 'mail'
                ? self::sendViaMail($recipients, $subject, $htmlBody)
                : self::sendViaSmtp($recipients, $subject, $htmlBody);
        } catch (\Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return false;
        }
    }

    /** @param string|array<int,string> $to @return array<int,string> */
    public static function normalizeRecipients($to): array
    {
        $list = is_array($to) ? $to : [$to];
        $out  = [];
        foreach ($list as $addr) {
            $addr = trim((string) $addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $out[$addr] = $addr; // dedupe
            }
        }
        return array_values($out);
    }

    public static function encodeHeader(string $value): string
    {
        // RFC 2047 for non-ASCII (accented Italian) header text.
        return preg_match('/[^\x20-\x7e]/', $value)
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }

    private static function fromHeader(): string
    {
        $addr = (string) Config::get('mail.from_address', 'no-reply@localhost');
        $name = (string) Config::get('mail.from_name', 'Gestionale Muratori');
        return self::encodeHeader($name) . ' <' . $addr . '>';
    }

    /**
     * Build a complete RFC 5322 MIME message (headers + HTML body) for the SMTP
     * DATA phase.
     *
     * @param array<int,string> $recipients
     */
    public static function buildMimeMessage(array $recipients, string $subject, string $htmlBody, string $date): string
    {
        $headers = [
            'Date: ' . $date,
            'From: ' . self::fromHeader(),
            'To: ' . implode(', ', $recipients),
            'Subject: ' . self::encodeHeader($subject),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        $body = rtrim(chunk_split(base64_encode($htmlBody), 76, "\r\n"));
        return implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n";
    }

    /** @param array<int,string> $recipients */
    private static function sendViaMail(array $recipients, string $subject, string $htmlBody): bool
    {
        $headers = implode("\r\n", [
            'From: ' . self::fromHeader(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ]);
        $body = rtrim(chunk_split(base64_encode($htmlBody), 76, "\r\n"));
        return mail(implode(', ', $recipients), '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
    }

    /** @param array<int,string> $recipients */
    private static function sendViaSmtp(array $recipients, string $subject, string $htmlBody): bool
    {
        $host = (string) Config::get('mail.host', '');
        $port = (int) Config::get('mail.port', 587);
        $enc  = (string) Config::get('mail.encryption', 'tls');
        if ($host === '') {
            throw new RuntimeException('MAIL_HOST is empty');
        }

        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $conn   = @stream_socket_client($remote, $errno, $errstr, (float) Config::get('mail.timeout', 10));
        if ($conn === false) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($conn, (int) Config::get('mail.timeout', 10));

        $ehloName = (string) (parse_url((string) Config::get('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'localhost');

        try {
            self::expect($conn, [220]);
            self::cmd($conn, 'EHLO ' . $ehloName, [250]);

            if ($enc === 'tls') {
                self::cmd($conn, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS negotiation failed');
                }
                self::cmd($conn, 'EHLO ' . $ehloName, [250]);
            }

            $user = (string) Config::get('mail.username', '');
            $pass = (string) Config::get('mail.password', '');
            if ($user !== '') {
                self::cmd($conn, 'AUTH LOGIN', [334]);
                self::cmd($conn, base64_encode($user), [334]);
                self::cmd($conn, base64_encode($pass), [235]);
            }

            self::cmd($conn, 'MAIL FROM:<' . Config::get('mail.from_address', 'no-reply@localhost') . '>', [250]);
            foreach ($recipients as $rcpt) {
                self::cmd($conn, 'RCPT TO:<' . $rcpt . '>', [250, 251]);
            }
            self::cmd($conn, 'DATA', [354]);

            $message = self::buildMimeMessage($recipients, $subject, $htmlBody, date('r'));
            // Dot-stuffing: a line starting with '.' must be escaped in the DATA phase.
            $message = preg_replace('/^\./m', '..', $message);
            fwrite($conn, $message . "\r\n.\r\n");
            self::expect($conn, [250]);

            self::cmd($conn, 'QUIT', [221]);
        } finally {
            fclose($conn);
        }
        return true;
    }

    /** @param resource $conn @param array<int,int> $expected */
    private static function cmd($conn, string $line, array $expected): void
    {
        fwrite($conn, $line . "\r\n");
        self::expect($conn, $expected);
    }

    /** @param resource $conn @param array<int,int> $expected */
    private static function expect($conn, array $expected): void
    {
        $response = '';
        while (($line = fgets($conn, 515)) !== false) {
            $response .= $line;
            // Multiline replies keep a '-' after the code until the final line.
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('Unexpected SMTP reply: ' . trim($response));
        }
    }
}
