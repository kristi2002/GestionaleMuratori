<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class InterventionMaterialModel
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO intervention_materials (intervention_id, item_id, qty_planned, qty_used, is_reserved)
             VALUES (:intervention_id, :item_id, :qty_planned, NULL, :is_reserved)'
        );
        $stmt->execute([
            ':intervention_id' => $data['intervention_id'],
            ':item_id'         => $data['item_id'],
            ':qty_planned'     => $data['qty_planned'],
            ':is_reserved'     => $data['is_reserved'] ? 1 : 0,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<int,array<string,mixed>> */
    public function forIntervention(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT m.*, w.name AS item_name, w.unit
             FROM intervention_materials m JOIN warehouse_items w ON w.id = m.item_id
             WHERE m.intervention_id = ?
             ORDER BY w.name'
        );
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll();
    }

    /** Reserved (not yet released/consumed) materials, locked for an in-transaction cancel/commit. */
    public function reservedForUpdate(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM intervention_materials WHERE intervention_id = ? AND is_reserved = 1 FOR UPDATE'
        );
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll();
    }

    public function markReleased(int $id): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE intervention_materials SET is_reserved = 0 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** All materials of an intervention, locked for the in-transaction completion commit (§4.2). */
    public function forInterventionForUpdate(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM intervention_materials WHERE intervention_id = ? FOR UPDATE'
        );
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll();
    }

    public function setUsed(int $id, string $qtyUsed): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE intervention_materials SET qty_used = ?, is_reserved = 0 WHERE id = ?'
        );
        return $stmt->execute([$qtyUsed, $id]);
    }
}
