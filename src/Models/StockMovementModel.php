<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class StockMovementModel
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO stock_movements (item_id, location_id, type, qty, intervention_id, purchase_order_line_id, user_id, note)
             VALUES (:item_id, :location_id, :type, :qty, :intervention_id, :purchase_order_line_id, :user_id, :note)'
        );
        $stmt->execute([
            ':item_id'                => $data['item_id'],
            // Defaults to the main warehouse so pre-multi-site callers stay correct.
            ':location_id'            => $data['location_id'] ?? StockLocationModel::MAIN_WAREHOUSE_ID,
            ':type'                   => $data['type'],
            ':qty'                    => $data['qty'],
            ':intervention_id'        => $data['intervention_id'] ?? null,
            ':purchase_order_line_id' => $data['purchase_order_line_id'] ?? null,
            ':user_id'                => $data['user_id'],
            ':note'                   => $data['note'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Ledger for a single item, most recent first (§5 ledger view). */
    public function forItem(int $itemId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT m.*, u.name AS user_name
             FROM stock_movements m JOIN users u ON u.id = m.user_id
             WHERE m.item_id = ?
             ORDER BY m.created_at DESC, m.id DESC'
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    /** Ledger rows created today (warehouse index KPI — read-only). */
    public function countToday(): int
    {
        return (int) Database::pdo()
            ->query('SELECT COUNT(*) FROM stock_movements WHERE created_at >= CURDATE()')
            ->fetchColumn();
    }

    /**
     * Materials actually used across a project (§5 report — "from ledger out
     * movements, not planned"), grouped by item.
     */
    public function usedByProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT w.name AS item_name, w.unit, SUM(m.qty) AS total_qty
             FROM stock_movements m
             JOIN warehouse_items w ON w.id = m.item_id
             JOIN interventions i ON i.id = m.intervention_id
             WHERE i.project_id = ? AND m.type = 'out'
             GROUP BY w.id, w.name, w.unit
             ORDER BY w.name"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }
}
