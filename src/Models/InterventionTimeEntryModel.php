<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Per-intervention work timers. A worker may have at most one running timer at a
 * time (ended_at IS NULL); duration is entry length, summed per intervention. Kept
 * separate from site_attendance (the per-cantiere clock-in that feeds the P&L).
 */
final class InterventionTimeEntryModel
{
    /** The worker's currently-running timer (any intervention), with its job title, or null. */
    public function runningForUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT t.*, i.title AS intervention_title
             FROM intervention_time_entries t
             JOIN interventions i ON i.id = t.intervention_id
             WHERE t.user_id = ? AND t.ended_at IS NULL
             ORDER BY t.id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Start a timer for the worker on an intervention; returns the new entry id. */
    public function start(int $interventionId, int $userId): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO intervention_time_entries (intervention_id, user_id, started_at)
             VALUES (?, ?, ?)'
        );
        $stmt->execute([$interventionId, $userId, date('Y-m-d H:i:s')]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Stop the worker's running timer on this intervention. Returns true if one was open. */
    public function stop(int $interventionId, int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE intervention_time_entries SET ended_at = ?
             WHERE intervention_id = ? AND user_id = ? AND ended_at IS NULL'
        );
        $stmt->execute([date('Y-m-d H:i:s'), $interventionId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Total logged seconds on an intervention (running entries counted up to now). */
    public function totalSeconds(int $interventionId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW()))), 0)
             FROM intervention_time_entries WHERE intervention_id = ?'
        );
        $stmt->execute([$interventionId]);
        return (int) $stmt->fetchColumn();
    }

    /** Elapsed seconds of a specific running entry (for the live client ticker). */
    public function elapsedSeconds(int $entryId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(TIMESTAMPDIFF(SECOND, started_at, NOW()), 0)
             FROM intervention_time_entries WHERE id = ?'
        );
        $stmt->execute([$entryId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Per-worker totals on an intervention, with each worker's hourly_rate so the view
     * can show a per-intervention labor estimate.
     *
     * @return array<int,array<string,mixed>> [{name, seconds, rate}]
     */
    public function perWorker(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(u.name, ?) AS name,
                    MAX(u.hourly_rate) AS rate,
                    SUM(TIMESTAMPDIFF(SECOND, t.started_at, COALESCE(t.ended_at, NOW()))) AS seconds,
                    MAX(t.ended_at IS NULL) AS running
             FROM intervention_time_entries t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.intervention_id = ?
             GROUP BY t.user_id
             ORDER BY seconds DESC'
        );
        $stmt->execute(['—', $interventionId]);
        return $stmt->fetchAll();
    }
}
