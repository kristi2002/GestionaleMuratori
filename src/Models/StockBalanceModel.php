<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Per-(item, location) balance cache — the location-scoped analogue of
 * warehouse_items.qty_in_stock. Like that cache, it is only ever written by
 * recompute() from the stock_movements ledger, never independently (§ inventory
 * ledger is the source of truth).
 */
final class StockBalanceModel
{
    /**
     * Recompute the cached balance for one (item, location) from the ledger and
     * upsert it. Same sign convention as WarehouseItemModel::recomputeStock, plus
     * transfer_in (+qty) / transfer_out (-qty). 'out' stays weight-0.
     */
    public function recompute(int $itemId, int $locationId): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(CASE
                WHEN type IN ('in', 'release', 'transfer_in') THEN qty
                WHEN type IN ('reserve', 'transfer_out') THEN -qty
                WHEN type = 'adjustment' THEN qty
                ELSE 0
             END), 0)
             FROM stock_movements WHERE item_id = ? AND location_id = ?"
        );
        $stmt->execute([$itemId, $locationId]);
        $total = (string) $stmt->fetchColumn();

        $upsert = Database::pdo()->prepare(
            'INSERT INTO stock_balances (item_id, location_id, qty)
             VALUES (:item_id, :location_id, :qty)
             ON DUPLICATE KEY UPDATE qty = VALUES(qty)'
        );
        $upsert->execute([
            ':item_id'     => $itemId,
            ':location_id' => $locationId,
            ':qty'         => $total,
        ]);

        return $total;
    }

    /** Cached balance for one (item, location); 0 if never stocked there. */
    public function qty(int $itemId, int $locationId): string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT qty FROM stock_balances WHERE item_id = ? AND location_id = ? LIMIT 1'
        );
        $stmt->execute([$itemId, $locationId]);
        $qty = $stmt->fetchColumn();
        return $qty === false ? '0.000' : (string) $qty;
    }

    /**
     * All non-empty balances for an item across locations, with location metadata.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forItem(int $itemId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT b.location_id, b.qty, l.name AS location_name, l.kind AS location_kind
             FROM stock_balances b
             JOIN stock_locations l ON l.id = b.location_id
             WHERE b.item_id = ?
             ORDER BY l.kind, l.name'
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }
}
