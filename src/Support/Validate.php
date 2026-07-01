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
}
