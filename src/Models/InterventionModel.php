<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class InterventionModel
{
    /**
     * @param array{project_id?:int,worker_id?:int,status?:string,date?:string,date_from?:string,date_to?:string,search?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function all(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);
        $sql = 'SELECT i.*, p.name AS project_name, c.name AS client_name, w.name AS worker_name
                FROM interventions i
                JOIN projects p ON p.id = i.project_id
                JOIN clients c ON c.id = p.client_id
                LEFT JOIN users w ON w.id = i.assigned_worker_id'
            . $where
            . ' ORDER BY i.scheduled_date IS NULL, i.scheduled_date DESC, i.scheduled_start_time, i.id DESC';

        if ($limit !== null) {
            // $limit/$offset are ints (Paginator), safe to inline for native prepares.
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Interventions scheduled within a date range, for the month calendar. */
    public function scheduledBetween(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.id, i.title, i.status, i.scheduled_date, i.scheduled_start_time,
                    p.name AS project_name, w.name AS worker_name
             FROM interventions i
             JOIN projects p ON p.id = i.project_id
             LEFT JOIN users w ON w.id = i.assigned_worker_id
             WHERE i.scheduled_date BETWEEN ? AND ?
             ORDER BY i.scheduled_date, i.scheduled_start_time, i.id'
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    /**
     * Active (non-completed, non-cancelled) scheduled interventions in a date window,
     * carrying the assigned worker id so the dispatch board can group by worker and
     * flag double-bookings (same worker, same day). Ordered worker → date → time,
     * with unassigned last.
     *
     * @return array<int,array<string,mixed>>
     */
    public function dispatchBetween(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.title, i.status, i.scheduled_date, i.scheduled_start_time,
                    i.assigned_worker_id, p.id AS project_id, p.name AS project_name,
                    w.name AS worker_name
             FROM interventions i
             JOIN projects p ON p.id = i.project_id
             LEFT JOIN users w ON w.id = i.assigned_worker_id
             WHERE i.scheduled_date BETWEEN ? AND ?
               AND i.status IN ('pending','in_progress','on_hold')
             ORDER BY i.assigned_worker_id IS NULL, i.assigned_worker_id,
                      i.scheduled_date, i.scheduled_start_time, i.id"
        );
        $stmt->execute([$from, $to]);
        return $stmt->fetchAll();
    }

    /** Reassign an intervention's worker (null = unassign). */
    public function reassign(int $id, ?int $workerId): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE interventions SET assigned_worker_id = ? WHERE id = ?');
        return $stmt->execute([$workerId, $id]);
    }

    /** Row count for the same filters (drives pagination). */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->filterSql($filters);
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM interventions i' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Shared WHERE builder for all()/count().
     *
     * @return array{0:string,1:array<int,mixed>} [" WHERE …", params]
     */
    private function filterSql(array $filters): array
    {
        $sql    = ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= ' AND i.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (!empty($filters['worker_id'])) {
            $sql .= ' AND i.assigned_worker_id = ?';
            $params[] = (int) $filters['worker_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['date'])) {
            $sql .= ' AND i.scheduled_date = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND i.scheduled_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND i.scheduled_date <= ?';
            $params[] = $filters['date_to'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND i.title LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }
        return [$sql, $params];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, p.name AS project_name, p.client_id AS project_client_id,
                    c.name AS client_name, w.name AS worker_name
             FROM interventions i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users w ON w.id = i.assigned_worker_id
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Locking read for use inside a transaction (§4.3 — prevents concurrent transition races). */
    public function findForUpdate(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM interventions WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Insert a new intervention; status always starts at 'pending' (DB default). */
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO interventions
                (project_id, assigned_worker_id, title, description, scheduled_date, scheduled_start_time)
             VALUES
                (:project_id, :assigned_worker_id, :title, :description, :scheduled_date, :scheduled_start_time)'
        );
        $stmt->execute([
            ':project_id'          => $data['project_id'],
            ':assigned_worker_id'  => $data['assigned_worker_id'],
            ':title'               => $data['title'],
            ':description'         => $data['description'],
            ':scheduled_date'      => $data['scheduled_date'],
            ':scheduled_start_time' => $data['scheduled_start_time'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Basic-field edit only — status changes go through the transition state machine. */
    public function updateBasic(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE interventions SET assigned_worker_id = :assigned_worker_id, title = :title,
                description = :description, scheduled_date = :scheduled_date,
                scheduled_start_time = :scheduled_start_time
             WHERE id = :id'
        );
        return $stmt->execute([
            ':assigned_worker_id'  => $data['assigned_worker_id'],
            ':title'               => $data['title'],
            ':description'         => $data['description'],
            ':scheduled_date'      => $data['scheduled_date'],
            ':scheduled_start_time' => $data['scheduled_start_time'],
            ':id'                  => $id,
        ]);
    }

    /** Sets the new status; started_at is only ever written once (first pending->in_progress). */
    public function setStatus(int $id, string $status, ?string $startedAt = null): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE interventions SET status = :status, started_at = COALESCE(started_at, :started_at)
             WHERE id = :id'
        );
        return $stmt->execute([
            ':status'     => $status,
            ':started_at' => $startedAt,
            ':id'         => $id,
        ]);
    }

    /** §4.2/§4.4 — completion is the only path to the 'completed' terminal status. */
    public function markCompleted(int $id, string $completedAt, ?string $completionNotes): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE interventions SET status = 'completed', completed_at = :completed_at,
                completion_notes = :completion_notes
             WHERE id = :id"
        );
        return $stmt->execute([
            ':completed_at'      => $completedAt,
            ':completion_notes'  => $completionNotes,
            ':id'                => $id,
        ]);
    }

    public function setSignaturePath(int $id, string $path): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE interventions SET client_signature_path = ? WHERE id = ?');
        return $stmt->execute([$path, $id]);
    }

    /**
     * Worker task list by tab (gap F4).
     *  - today:    scheduled for today (any status)
     *  - upcoming: open tasks scheduled after today, or with no date
     *  - done:     completed in the last 14 days
     *
     * @return array<int,array<string,mixed>>
     */
    public function forWorkerTab(int $workerId, string $tab, string $today): array
    {
        $base = 'SELECT i.*, p.name AS project_name, c.name AS client_name
                 FROM interventions i
                 JOIN projects p ON p.id = i.project_id
                 JOIN clients c ON c.id = p.client_id
                 WHERE i.assigned_worker_id = ?';

        if ($tab === 'upcoming') {
            $sql = $base . " AND i.status IN ('pending','in_progress','on_hold')
                             AND (i.scheduled_date > ? OR i.scheduled_date IS NULL)
                    ORDER BY i.scheduled_date IS NULL, i.scheduled_date, i.scheduled_start_time, i.id";
            $params = [$workerId, $today];
        } elseif ($tab === 'done') {
            $sql = $base . " AND i.status = 'completed'
                             AND i.completed_at >= DATE_SUB(?, INTERVAL 14 DAY)
                    ORDER BY i.completed_at DESC";
            $params = [$workerId, $today];
        } else { // today
            $sql = $base . ' AND i.scheduled_date = ?
                    ORDER BY i.scheduled_start_time, i.id';
            $params = [$workerId, $today];
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Open (non-terminal) interventions count — dashboard KPI. */
    public function countOpen(): int
    {
        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM interventions WHERE status IN ('pending','in_progress','on_hold')"
        );
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,int> status => count for a given scheduled date — dashboard KPI. */
    public function countsByStatusForDate(string $date): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, COUNT(*) AS n FROM interventions WHERE scheduled_date = ? GROUP BY status'
        );
        $stmt->execute([$date]);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        return $counts;
    }

    /**
     * Aggregate counts for the interventions list/calendar KPI row, all in one
     * round-trip:
     *  - today:            scheduled for $today (any status)
     *  - week:             scheduled within [$weekFrom, $weekTo]
     *  - overdue:          past-due & still open (scheduled_date < today, non-terminal)
     *  - completed_month:  completed within [$monthFrom, $monthTo]
     *
     * @return array{today:int,week:int,overdue:int,completed_month:int}
     */
    public function summaryCounts(
        string $today,
        string $weekFrom,
        string $weekTo,
        string $monthFrom,
        string $monthTo
    ): array {
        $stmt = Database::pdo()->prepare(
            "SELECT
                (SELECT COUNT(*) FROM interventions WHERE scheduled_date = :today) AS today,
                (SELECT COUNT(*) FROM interventions WHERE scheduled_date BETWEEN :week_from AND :week_to) AS week,
                (SELECT COUNT(*) FROM interventions
                    WHERE scheduled_date < :overdue_before
                      AND status IN ('pending','in_progress','on_hold')) AS overdue,
                (SELECT COUNT(*) FROM interventions
                    WHERE completed_at IS NOT NULL
                      AND DATE(completed_at) BETWEEN :month_from AND :month_to) AS completed_month"
        );
        $stmt->execute([
            ':today'          => $today,
            ':week_from'      => $weekFrom,
            ':week_to'        => $weekTo,
            ':overdue_before' => $today,
            ':month_from'     => $monthFrom,
            ':month_to'       => $monthTo,
        ]);
        $row = $stmt->fetch() ?: [];
        return [
            'today'           => (int) ($row['today'] ?? 0),
            'week'            => (int) ($row['week'] ?? 0),
            'overdue'         => (int) ($row['overdue'] ?? 0),
            'completed_month' => (int) ($row['completed_month'] ?? 0),
        ];
    }

    /**
     * Per-status counts honoring the current project/worker/date filters (but
     * ignoring the status filter itself) — drives the pill filter badges.
     *
     * @param array<string,mixed> $filters
     * @return array<string,int> status => count
     */
    public function countsByStatus(array $filters = []): array
    {
        unset($filters['status']);
        [$where, $params] = $this->filterSql($filters);
        $stmt = Database::pdo()->prepare(
            'SELECT i.status, COUNT(*) AS n FROM interventions i' . $where . ' GROUP BY i.status'
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * Per-day intervention counts over a date window, for the dashboard trend
     * sparklines. $column is whitelisted to avoid injection: 'scheduled_date'
     * counts by planned day; 'completed_at' counts real completions per day.
     *
     * @return array<string,int> 'Y-m-d' => count (only non-zero days present)
     */
    public function dailyCounts(string $column, string $from, string $to): array
    {
        if ($column === 'completed_at') {
            $sql = 'SELECT DATE(completed_at) AS d, COUNT(*) AS n FROM interventions
                    WHERE completed_at IS NOT NULL AND DATE(completed_at) BETWEEN ? AND ?
                    GROUP BY d';
        } else {
            $sql = 'SELECT scheduled_date AS d, COUNT(*) AS n FROM interventions
                    WHERE scheduled_date BETWEEN ? AND ? GROUP BY d';
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$from, $to]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['d']] = (int) $row['n'];
        }
        return $out;
    }

    /** Full transition audit for the admin detail page, oldest first. */
    public function statusHistory(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT h.*, u.name AS changed_by_name
             FROM intervention_status_history h
             JOIN users u ON u.id = h.changed_by
             WHERE h.intervention_id = ?
             ORDER BY h.changed_at, h.id'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
