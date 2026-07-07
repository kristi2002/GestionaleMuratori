<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Shared input validation helpers (§8 polish — caught via manual testing: MySQL
 * here runs without STRICT_TRANS_TABLES, so an out-of-range DECIMAL silently
 * clamps to the column's max instead of erroring. Every qty-like column in the
 * schema is DECIMAL(12,3), so the application must reject the overflow itself).
 */
final class Validate
{
    /** DECIMAL(12,3) ceiling shared by qty_planned, qty_used, qty_in_stock, reorder_level, stock_movements.qty. */
    public const MAX_DECIMAL = 999999999.999;

    public static function isQty(string $raw): bool
    {
        return $raw !== '' && is_numeric($raw) && abs((float) $raw) <= self::MAX_DECIMAL;
    }

    /** WGS84 latitude in [-90, 90]. Empty is invalid; callers treat GPS as optional. */
    public static function isLatitude(string $raw): bool
    {
        return $raw !== '' && is_numeric($raw) && (float) $raw >= -90.0 && (float) $raw <= 90.0;
    }

    /** WGS84 longitude in [-180, 180]. */
    public static function isLongitude(string $raw): bool
    {
        return $raw !== '' && is_numeric($raw) && (float) $raw >= -180.0 && (float) $raw <= 180.0;
    }

    /** Strict ISO date (Y-m-d) that round-trips — rejects things like 2026-02-31. */
    public static function isDate(string $raw): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $raw);
        return $d !== false && $d->format('Y-m-d') === $raw;
    }

    /** Non-negative money amount within the shared DECIMAL(12,x) ceiling. */
    public static function isMoney(string $raw): bool
    {
        return $raw !== '' && is_numeric($raw) && (float) $raw >= 0 && (float) $raw <= self::MAX_DECIMAL;
    }
}
