<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Giornale dei Lavori (DPR 380/2001): one immutable-once-closed daily log per
 * (project, date), recording weather, workforce, equipment and work performed.
 * A closed log (is_closed=1) is legally locked — the controller refuses edits.
 */
final class DailyLogModel
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT dl.*, p.name AS project_name, u.name AS created_by_name, cu.name AS closed_by_name
             FROM daily_logs dl
             JOIN projects p ON p.id = dl.project_id
             JOIN users u ON u.id = dl.created_by
             LEFT JOIN users cu ON cu.id = dl.closed_by
             WHERE dl.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findForProjectDate(int $projectId, string $date): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM daily_logs WHERE project_id = ? AND log_date = ? LIMIT 1'
        );
        $stmt->execute([$projectId, $date]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> logs for a project, newest day first */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM daily_logs WHERE project_id = ? ORDER BY log_date DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO daily_logs
                (project_id, log_date, weather_text, weather_code, temp_min, temp_max,
                 workers_present, work_done, notes, created_by)
             VALUES
                (:project_id, :log_date, :weather_text, :weather_code, :temp_min, :temp_max,
                 :workers_present, :work_done, :notes, :created_by)'
        );
        $stmt->execute([
            ':project_id'      => $data['project_id'],
            ':log_date'        => $data['log_date'],
            ':weather_text'    => $data['weather_text'] ?? null,
            ':weather_code'    => $data['weather_code'] ?? null,
            ':temp_min'        => $data['temp_min'] ?? null,
            ':temp_max'        => $data['temp_max'] ?? null,
            ':workers_present' => $data['workers_present'] ?? null,
            ':work_done'       => $data['work_done'] ?? null,
            ':notes'           => $data['notes'] ?? null,
            ':created_by'      => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Update the editable fields of an OPEN log (caller must verify !is_closed). */
    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE daily_logs SET weather_text = :weather_text, weather_code = :weather_code,
                temp_min = :temp_min, temp_max = :temp_max, workers_present = :workers_present,
                work_done = :work_done, notes = :notes
             WHERE id = :id AND is_closed = 0'
        );
        return $stmt->execute([
            ':weather_text'    => $data['weather_text'] ?? null,
            ':weather_code'    => $data['weather_code'] ?? null,
            ':temp_min'        => $data['temp_min'] ?? null,
            ':temp_max'        => $data['temp_max'] ?? null,
            ':workers_present' => $data['workers_present'] ?? null,
            ':work_done'       => $data['work_done'] ?? null,
            ':notes'           => $data['notes'] ?? null,
            ':id'              => $id,
        ]);
    }

    /** Lock the log for legal integrity. No-op if already closed. */
    public function close(int $id, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE daily_logs SET is_closed = 1, closed_at = NOW(), closed_by = :uid
             WHERE id = :id AND is_closed = 0'
        );
        return $stmt->execute([':uid' => $userId, ':id' => $id]);
    }

    /** Equipment ids attached to a log. @return array<int,int> */
    public function equipmentIds(int $logId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT equipment_id FROM daily_log_equipment WHERE daily_log_id = ?'
        );
        $stmt->execute([$logId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Full equipment rows attached to a log. @return array<int,array<string,mixed>> */
    public function equipmentFor(int $logId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.* FROM daily_log_equipment dle
             JOIN equipment e ON e.id = dle.equipment_id
             WHERE dle.daily_log_id = ? ORDER BY e.name'
        );
        $stmt->execute([$logId]);
        return $stmt->fetchAll();
    }

    /** Replace the log's equipment set (only allowed while open — caller checks). */
    public function syncEquipment(int $logId, array $equipmentIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM daily_log_equipment WHERE daily_log_id = ?')->execute([$logId]);
            $ins = $pdo->prepare(
                'INSERT INTO daily_log_equipment (daily_log_id, equipment_id) VALUES (?, ?)'
            );
            foreach (array_unique(array_map('intval', $equipmentIds)) as $eid) {
                if ($eid > 0) {
                    $ins->execute([$logId, $eid]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
