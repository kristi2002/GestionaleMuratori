<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class WarehouseItemModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = ''): array
    {
        if ($search !== '') {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM warehouse_items WHERE name LIKE ? OR sku LIKE ? ORDER BY name'
            );
            $like = '%' . $search . '%';
            $stmt->execute([$like, $like]);
        } else {
            $stmt = Database::pdo()->query('SELECT * FROM warehouse_items ORDER BY name');
        }
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM warehouse_items WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Locking read for use inside a transaction (§4.1/§4.2 — prevents concurrent stock corruption). */
    public function findForUpdate(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM warehouse_items WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function skuExists(string $sku, ?int $excludeId = null): bool
    {
        if ($sku === '') {
            return false;
        }
        $sql    = 'SELECT COUNT(*) FROM warehouse_items WHERE sku = ?';
        $params = [$sku];
        if ($excludeId !== null) {
            $sql      .= ' AND id != ?';
            $params[]  = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO warehouse_items (name, sku, unit, qty_in_stock, reorder_level, is_active)
             VALUES (:name, :sku, :unit, 0, :reorder_level, 1)'
        );
        $stmt->execute([
            ':name'          => $data['name'],
            ':sku'           => $data['sku'],
            ':unit'          => $data['unit'],
            ':reorder_level' => $data['reorder_level'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE warehouse_items SET name = :name, sku = :sku, unit = :unit, reorder_level = :reorder_level
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'          => $data['name'],
            ':sku'           => $data['sku'],
            ':unit'          => $data['unit'],
            ':reorder_level' => $data['reorder_level'],
            ':id'            => $id,
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE warehouse_items SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    /**
     * Active items at or below their reorder level (dashboard low-stock alert).
     * Items with reorder_level = 0 are excluded: no threshold configured.
     *
     * @return array<int,array<string,mixed>>
     */
    public function lowStock(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT * FROM warehouse_items
             WHERE is_active = 1 AND reorder_level > 0 AND qty_in_stock <= reorder_level
             ORDER BY qty_in_stock / reorder_level, name'
        );
        return $stmt->fetchAll();
    }

    /**
     * Recompute qty_in_stock from the stock_movements ledger (§4.1 reconciliation).
     * qty_in_stock tracks the MAIN WAREHOUSE (location 1) balance: every pre-multi-site
     * movement lives at location 1, and interventions reserve there by default, so this
     * stays the "how much do we hold in the depot" figure the dashboard/low-stock use.
     * Sign convention: in/release/transfer_in add, reserve/transfer_out subtract,
     * adjustment carries its own signed delta. 'out' is intentionally weight-0 here:
     * stock is already decremented by the matching 'reserve' at intervention creation,
     * and 'release' (qty_planned - qty_used) corrects it back to -qty_used net at
     * completion. 'out' rows stay in the ledger purely as the §5 reporting audit trail.
     */
    public function recomputeStock(int $id): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COALESCE(SUM(CASE
                WHEN type IN ('in', 'release', 'transfer_in') THEN qty
                WHEN type IN ('reserve', 'transfer_out') THEN -qty
                WHEN type = 'adjustment' THEN qty
                ELSE 0
             END), 0) FROM stock_movements WHERE item_id = ? AND location_id = ?"
        );
        $stmt->execute([$id, StockLocationModel::MAIN_WAREHOUSE_ID]);
        $total = (string) $stmt->fetchColumn();

        $update = Database::pdo()->prepare('UPDATE warehouse_items SET qty_in_stock = ? WHERE id = ?');
        $update->execute([$total, $id]);

        return $total;
    }

    /**
     * Refresh the stock caches after a movement at $locationId: always the
     * per-(item, location) balance, plus qty_in_stock when the movement touched the
     * main warehouse. Call this from every writer that inserts a stock_movements row,
     * inside its transaction, so no cache drifts from the ledger.
     */
    public function refreshCaches(int $itemId, int $locationId): void
    {
        (new StockBalanceModel())->recompute($itemId, $locationId);
        if ($locationId === StockLocationModel::MAIN_WAREHOUSE_ID) {
            $this->recomputeStock($itemId);
        }
    }
}
