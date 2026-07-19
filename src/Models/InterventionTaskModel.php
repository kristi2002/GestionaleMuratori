<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Checklist / punch-list items on an intervention (child of interventions). Admins
 * define them; the assigned worker ticks them off on site. `setDone()` takes an
 * ABSOLUTE state (not a flip) so a replayed offline-queued toggle is idempotent.
 */
final class InterventionTaskModel
{
    /** @return array<int,array<string,mixed>> ordered items for one intervention */
    public function forIntervention(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM intervention_tasks WHERE intervention_id = ? ORDER BY position, id'
        );
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM intervention_tasks WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array{intervention_id:int,label:string,created_by:int} $data */
    public function create(array $data): int
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), 0) + 1 FROM intervention_tasks WHERE intervention_id = ?');
        $stmt->execute([$data['intervention_id']]);
        $position = (int) $stmt->fetchColumn();

        $ins = $pdo->prepare(
            'INSERT INTO intervention_tasks (intervention_id, label, position, created_by)
             VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$data['intervention_id'], $data['label'], $position, $data['created_by']]);
        return (int) $pdo->lastInsertId();
    }

    /** Set the absolute done state (idempotent — safe to replay an offline toggle). */
    public function setDone(int $id, bool $done, ?int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE intervention_tasks SET is_done = ?, done_by = ?, done_at = ? WHERE id = ?'
        );
        return $stmt->execute([
            $done ? 1 : 0,
            $done ? $userId : null,
            $done ? date('Y-m-d H:i:s') : null,
            $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM intervention_tasks WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @return array{done:int,total:int} */
    public function progressForIntervention(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(is_done), 0) AS done
             FROM intervention_tasks WHERE intervention_id = ?'
        );
        $stmt->execute([$interventionId]);
        $row = $stmt->fetch();
        return ['done' => (int) $row['done'], 'total' => (int) $row['total']];
    }

    /**
     * Batch progress for a set of interventions (avoids N+1 on list pages).
     *
     * @param array<int,int> $interventionIds
     * @return array<int,array{done:int,total:int}> intervention_id => progress
     */
    public function progressForInterventions(array $interventionIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $interventionIds)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT intervention_id, COUNT(*) AS total, COALESCE(SUM(is_done), 0) AS done
             FROM intervention_tasks WHERE intervention_id IN ({$placeholders})
             GROUP BY intervention_id"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['intervention_id']] = ['done' => (int) $row['done'], 'total' => (int) $row['total']];
        }
        return $out;
    }
}
