<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Time-based One-Time Password (TOTP, RFC 6238) — dependency-free, compatible with
 * Google Authenticator / Authy / FreeOTP (HMAC-SHA1, 30s step, 6 digits). Used for
 * two-factor login. The pure functions (code/verify/base32*) are unit-testable
 * against the RFC 6238 test vectors.
 */
final class Totp
{
    private const PERIOD   = 30;
    private const DIGITS   = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A fresh base32 shared secret (default 160-bit, as recommended). */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** The TOTP code for a given time (counterOffset shifts by whole periods). */
    public static function code(string $secret, ?int $timestamp = null, int $counterOffset = 0): string
    {
        $key     = self::base32Decode($secret);
        $counter = (int) floor(($timestamp ?? time()) / self::PERIOD) + $counterOffset;
        // 8-byte big-endian counter (high 32 bits are zero for any realistic time).
        $binCounter = pack('N', 0) . pack('N', $counter);

        $hash   = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0f;
        $part   = ((ord($hash[$offset]) & 0x7f) << 24)
                | ((ord($hash[$offset + 1]) & 0xff) << 16)
                | ((ord($hash[$offset + 2]) & 0xff) << 8)
                | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Constant-time check of a submitted code against ±$window periods (clock drift). */
    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1): bool
    {
        $code = (string) preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $timestamp, $i), $code)) {
                return true;
            }
        }
        return false;
    }

    /** otpauth:// provisioning URI (paste into an authenticator or render as a QR). */
    public static function otpauthUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function base32Encode(string $bin): string
    {
        if ($bin === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($bin) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = (string) preg_replace('/[^A-Z2-7]/', '', strtoupper($b32));
        if ($b32 === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }
        return $out;
    }
}
