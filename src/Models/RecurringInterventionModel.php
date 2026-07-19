<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Recurring intervention templates (maintenance plans). The scheduler
 * (App\Services\SchedulerService) materialises real interventions from these on
 * their due date and advances next_run_date. A single current schedule is kept
 * per row; generation is idempotent because advancing next_run_date past today
 * means a same-day re-run finds nothing due.
 */
final class RecurringInterventionModel
{
    /** @return array<int,array<string,mixed>> list rows with project + worker names */
    public function all(): array
    {
        return Database::pdo()->query(
            "SELECT r.*, p.name AS project_name, c.name AS client_name, w.name AS worker_name
             FROM recurring_interventions r
             JOIN projects p ON p.id = r.project_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users w ON w.id = r.assigned_worker_id
             ORDER BY r.is_active DESC, r.next_run_date"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM recurring_interventions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Active plans whose next occurrence is due on/before $today. @return array<int,array<string,mixed>> */
    public function due(string $today): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM recurring_interventions
             WHERE is_active = 1 AND next_run_date <= ?
               AND (end_date IS NULL OR start_date <= end_date)
             ORDER BY id'
        );
        $stmt->execute([$today]);
        return $stmt->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO recurring_interventions
                (project_id, assigned_worker_id, title, description, frequency, interval_count,
                 scheduled_start_time, start_date, next_run_date, end_date, created_by)
             VALUES (:project_id, :worker_id, :title, :description, :frequency, :interval_count,
                 :start_time, :start_date, :next_run_date, :end_date, :created_by)'
        );
        $stmt->execute($this->bind($data) + [':created_by' => $data['created_by']]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE recurring_interventions SET
                project_id = :project_id, assigned_worker_id = :worker_id, title = :title,
                description = :description, frequency = :frequency, interval_count = :interval_count,
                scheduled_start_time = :start_time, start_date = :start_date,
                next_run_date = :next_run_date, end_date = :end_date
             WHERE id = :id'
        );
        return $stmt->execute($this->bind($data) + [':id' => $id]);
    }

    /** Persist the advanced schedule after generation. */
    public function advance(int $id, string $nextRunDate, bool $active): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE recurring_interventions
             SET next_run_date = ?, last_generated_at = NOW(), is_active = ?
             WHERE id = ?'
        );
        return $stmt->execute([$nextRunDate, $active ? 1 : 0, $id]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE recurring_interventions SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM recurring_interventions WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function bind(array $data): array
    {
        return [
            ':project_id'     => $data['project_id'],
            ':worker_id'      => $data['assigned_worker_id'],
            ':title'          => $data['title'],
            ':description'    => $data['description'],
            ':frequency'      => $data['frequency'],
            ':interval_count' => $data['interval_count'],
            ':start_time'     => $data['scheduled_start_time'],
            ':start_date'     => $data['start_date'],
            ':next_run_date'  => $data['next_run_date'],
            ':end_date'       => $data['end_date'],
        ];
    }
}
