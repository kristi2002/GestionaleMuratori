<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Italian fiscal-identifier helpers, shared by client/company validation and the
 * FatturaPA XML builder. Validation is format+checksum only — it can tell a
 * malformed Partita IVA from a plausible one, not whether the number is really
 * registered (that is the Agenzia delle Entrate's job).
 */
final class Fiscal
{
    /** Regime fiscale codes (FatturaPA RegimeFiscale). RF19 = regime forfettario. */
    public const REGIMI = [
        'RF01', 'RF02', 'RF04', 'RF05', 'RF06', 'RF07', 'RF08', 'RF09', 'RF10',
        'RF11', 'RF12', 'RF13', 'RF14', 'RF15', 'RF16', 'RF17', 'RF18', 'RF19',
    ];

    /**
     * Partita IVA: 11 digits with the standard Luhn-style check digit.
     * (The last digit validates the first ten.)
     */
    public static function isPartitaIva(string $raw): bool
    {
        $v = preg_replace('/\s+/', '', $raw) ?? '';
        if (!preg_match('/^\d{11}$/', $v)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $d = (int) $v[$i];
            if ($i % 2 === 1) {          // even position (1-indexed) → double
                $d *= 2;
                if ($d > 9) {
                    $d -= 9;
                }
            }
            $sum += $d;
        }
        $check = (10 - ($sum % 10)) % 10;
        return $check === (int) $v[10];
    }

    /**
     * Codice fiscale: either the 16-char personal form (6 letters, 2 digits, …)
     * or an 11-digit numeric form (companies use their Partita IVA as C.F.).
     * The personal form is validated by shape and its final control character.
     */
    public static function isCodiceFiscale(string $raw): bool
    {
        $v = strtoupper(preg_replace('/\s+/', '', $raw) ?? '');
        if (preg_match('/^\d{11}$/', $v)) {
            return self::isPartitaIva($v);
        }
        if (!preg_match('/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/', $v)) {
            return false;
        }
        return $v[15] === self::cfControlChar(substr($v, 0, 15));
    }

    /** SdI recipient code: 6 alphanumerics (private) or 7 (PA), or the fallback 0000000. */
    public static function isCodiceDestinatario(string $raw): bool
    {
        $v = strtoupper(trim($raw));
        return (bool) preg_match('/^[A-Z0-9]{6,7}$/', $v);
    }

    /** Two-letter Italian province code (uppercased). */
    public static function isProvincia(string $raw): bool
    {
        return (bool) preg_match('/^[A-Za-z]{2}$/', trim($raw));
    }

    /** Codice fiscale control character (15 → 16th char), per the odd/even tables. */
    private static function cfControlChar(string $first15): string
    {
        $odd = [
            '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15,
            '7' => 17, '8' => 19, '9' => 21, 'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7,
            'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21, 'K' => 2,
            'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8,
            'S' => 12, 'T' => 14, 'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
        ];
        $even = [
            '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6,
            '7' => 7, '8' => 8, '9' => 9, 'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3,
            'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9, 'K' => 10,
            'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17,
            'S' => 18, 'T' => 19, 'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
        ];
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $c = $first15[$i];
            $sum += ($i % 2 === 0) ? $odd[$c] : $even[$c];
        }
        return chr(ord('A') + ($sum % 26));
    }
}
