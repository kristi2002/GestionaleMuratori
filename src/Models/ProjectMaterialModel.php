<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ProjectMaterialModel
{
    /** @return array<int,array<string,mixed>> Materials logged directly on a project, newest first. */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT pm.*, w.name AS item_name, w.unit, u.name AS created_by_name
             FROM project_materials pm
             JOIN warehouse_items w ON w.id = pm.item_id
             JOIN users u ON u.id = pm.created_by
             WHERE pm.project_id = ?
             ORDER BY pm.created_at DESC, pm.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_materials WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO project_materials (project_id, item_id, qty, note, created_by)
             VALUES (:project_id, :item_id, :qty, :note, :created_by)'
        );
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':item_id'    => $data['item_id'],
            ':qty'        => $data['qty'],
            ':note'       => $data['note'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_materials WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Per-item totals of everything consumed on a project: materials logged
     * directly here plus the ones recorded on its interventions (qty_used once
     * recorded, the planned amount otherwise; cancelled interventions skipped).
     *
     * @return array<int,array{item_name:string,unit:string,total_qty:string}>
     */
    public function summaryForProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.item_name, t.unit, SUM(t.qty) AS total_qty
             FROM (
                 SELECT w.name AS item_name, w.unit, pm.qty
                 FROM project_materials pm
                 JOIN warehouse_items w ON w.id = pm.item_id
                 WHERE pm.project_id = ?
                 UNION ALL
                 SELECT w.name, w.unit, COALESCE(m.qty_used, m.qty_planned)
                 FROM intervention_materials m
                 JOIN interventions i ON i.id = m.intervention_id
                 JOIN warehouse_items w ON w.id = m.item_id
                 WHERE i.project_id = ? AND i.status <> \'cancelled\'
             ) t
             GROUP BY t.item_name, t.unit
             ORDER BY t.item_name'
        );
        $stmt->execute([$projectId, $projectId]);
        return $stmt->fetchAll();
    }
}
