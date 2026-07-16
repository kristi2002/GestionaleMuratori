<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Buoni d'Ordine (purchase orders): supplier-facing orders with line items.
 * Header + line structure mirrors QuoteModel; the difference is the counterparty is a
 * supplier and each line may reference a warehouse item so a delivery can be received
 * into stock. "Received" quantities are never stored — they are summed from the
 * stock_movements ledger (source of truth), keyed by purchase_order_line_id.
 */
final class PurchaseOrderModel
{
    /**
     * @param array{search?:string,status?:string,supplier_id?:int,project_id?:int} $filters
     * @return array<int,array<string,mixed>> POs with supplier/project names and computed subtotal.
     */
    public function all(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);
        $sql = 'SELECT o.*, s.name AS supplier_name, p.name AS project_name,
                       (SELECT COALESCE(SUM(l.qty * l.unit_price), 0)
                          FROM purchase_order_lines l WHERE l.purchase_order_id = o.id) AS subtotal
                FROM purchase_orders o
                JOIN suppliers s ON s.id = o.supplier_id
                LEFT JOIN projects p ON p.id = o.project_id'
            . $where
            . ' ORDER BY o.order_date DESC, o.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Row count for the same filters (drives pagination). */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->filterSql($filters);
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM purchase_orders o JOIN suppliers s ON s.id = o.supplier_id' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Real per-status counts over every order (drives the pill filter badges).
     * @return array<string,int>
     */
    public function statusCounts(): array
    {
        $stmt = Database::pdo()->query('SELECT status, COUNT(*) AS n FROM purchase_orders GROUP BY status');
        $out  = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * Header KPI aggregates over the whole table: total orders, how many are still
     * open (not received/cancelled), how many are awaiting delivery (sent/confirmed),
     * and the VAT-included value ordered.
     * @return array<string,string> Numeric strings keyed by metric.
     */
    public function summary(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN o.status IN ('draft','sent','confirmed','partially_received') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN o.status IN ('sent','confirmed') THEN 1 ELSE 0 END) AS awaiting_count,
                COALESCE(SUM(l.subtotal * (1 + o.vat_rate / 100)), 0) AS total_value
             FROM purchase_orders o
             LEFT JOIN (
                SELECT purchase_order_id, SUM(qty * unit_price) AS subtotal
                FROM purchase_order_lines GROUP BY purchase_order_id
             ) l ON l.purchase_order_id = o.id"
        );
        $row = $stmt->fetch();
        return $row ? array_map(static fn ($v): string => (string) $v, $row) : [];
    }

    /** @return array{0:string,1:array<int,mixed>} Shared WHERE builder for all()/count(). */
    private function filterSql(array $filters): array
    {
        $sql    = ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql     .= ' AND (o.number LIKE ? OR o.title LIKE ? OR s.name LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['status'])) {
            $sql     .= ' AND o.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['supplier_id'])) {
            $sql     .= ' AND o.supplier_id = ?';
            $params[] = (int) $filters['supplier_id'];
        }
        if (!empty($filters['project_id'])) {
            $sql     .= ' AND o.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        return [$sql, $params];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.*, s.name AS supplier_name, s.address AS supplier_address,
                    s.vat_or_tax_id AS supplier_vat, s.email AS supplier_email,
                    p.name AS project_name, loc.name AS location_name, u.name AS created_by_name
             FROM purchase_orders o
             JOIN suppliers s ON s.id = o.supplier_id
             LEFT JOIN projects p ON p.id = o.project_id
             JOIN stock_locations loc ON loc.id = o.location_id
             JOIN users u ON u.id = o.created_by
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> Line items in display order (plain, for the edit form). */
    public function lines(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT l.*, w.name AS item_name, w.unit AS item_unit
             FROM purchase_order_lines l
             LEFT JOIN warehouse_items w ON w.id = l.item_id
             WHERE l.purchase_order_id = ? ORDER BY l.sort_order, l.id'
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Line items enriched with the quantity already received, summed from the ledger
     * (type='in' rows tagged with the line id). Drives the receive screen and the
     * per-line delivery progress. Only lines with an item_id can be received.
     *
     * @return array<int,array<string,mixed>>
     */
    public function linesWithReceived(int $orderId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT l.*, w.name AS item_name, w.unit AS item_unit,
                    (SELECT COALESCE(SUM(m.qty), 0)
                       FROM stock_movements m
                      WHERE m.purchase_order_line_id = l.id AND m.type = 'in') AS qty_received
             FROM purchase_order_lines l
             LEFT JOIN warehouse_items w ON w.id = l.item_id
             WHERE l.purchase_order_id = ? ORDER BY l.sort_order, l.id"
        );
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $lines */
    public function create(array $data, array $lines): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO purchase_orders
                    (supplier_id, project_id, location_id, number, title, order_date, expected_date, status, vat_rate, notes, created_by)
                 VALUES (:supplier_id, :project_id, :location_id, :number, :title, :order_date, :expected_date, :status, :vat_rate, :notes, :created_by)'
            );
            $stmt->execute([
                ':supplier_id'   => $data['supplier_id'],
                ':project_id'    => $data['project_id'],
                ':location_id'   => $data['location_id'],
                ':number'        => $data['number'],
                ':title'         => $data['title'],
                ':order_date'    => $data['order_date'],
                ':expected_date' => $data['expected_date'],
                ':status'        => $data['status'],
                ':vat_rate'      => $data['vat_rate'],
                ':notes'         => $data['notes'],
                ':created_by'    => $data['created_by'],
            ]);
            $id = (int) $pdo->lastInsertId();
            $this->insertLines($id, $lines);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $lines Replaces all existing lines.
     * Received-stock lines are protected by the controller (no edit once deliveries exist).
     */
    public function update(int $id, array $data, array $lines): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE purchase_orders SET supplier_id = :supplier_id, project_id = :project_id,
                    location_id = :location_id, number = :number, title = :title, order_date = :order_date,
                    expected_date = :expected_date, status = :status, vat_rate = :vat_rate, notes = :notes
                 WHERE id = :id'
            );
            $stmt->execute([
                ':supplier_id'   => $data['supplier_id'],
                ':project_id'    => $data['project_id'],
                ':location_id'   => $data['location_id'],
                ':number'        => $data['number'],
                ':title'         => $data['title'],
                ':order_date'    => $data['order_date'],
                ':expected_date' => $data['expected_date'],
                ':status'        => $data['status'],
                ':vat_rate'      => $data['vat_rate'],
                ':notes'         => $data['notes'],
                ':id'            => $id,
            ]);
            $pdo->prepare('DELETE FROM purchase_order_lines WHERE purchase_order_id = ?')->execute([$id]);
            $this->insertLines($id, $lines);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM purchase_orders WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** True when any delivery has been received against this order (blocks edit/delete). */
    public function hasReceipts(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM stock_movements m
             JOIN purchase_order_lines l ON l.id = m.purchase_order_line_id
             WHERE l.purchase_order_id = ? AND m.type = 'in'"
        );
        $stmt->execute([$id]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** Persist a header status directly (used by the receipt service after a delivery). */
    public function setStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
    }

    /**
     * Orders attached to a project, newest first, with subtotal and a received flag —
     * feeds the "Materiali Ordinati" panel on the project page.
     * @return array<int,array<string,mixed>>
     */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT o.*, s.name AS supplier_name,
                    (SELECT COALESCE(SUM(l.qty * l.unit_price), 0)
                       FROM purchase_order_lines l WHERE l.purchase_order_id = o.id) AS subtotal
             FROM purchase_orders o
             JOIN suppliers s ON s.id = o.supplier_id
             WHERE o.project_id = ?
             ORDER BY o.order_date DESC, o.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /**
     * Suggested next number for the create form, e.g. "2026/003".
     * Based on the highest suffix already issued this year (not a row count),
     * so deleting an order can never cause a number to be re-used.
     */
    public function nextNumberSuggestion(): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED))
             FROM purchase_orders WHERE number LIKE CONCAT(YEAR(CURDATE()), '/%')"
        );
        $stmt->execute();
        return date('Y') . '/' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function insertLines(int $orderId, array $lines): void
    {
        $insert = Database::pdo()->prepare(
            'INSERT INTO purchase_order_lines (purchase_order_id, item_id, description, qty, unit, unit_price, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (array_values($lines) as $i => $line) {
            $insert->execute([
                $orderId,
                $line['item_id'],
                $line['description'],
                $line['qty'],
                $line['unit'],
                $line['unit_price'],
                $i,
            ]);
        }
    }
}
