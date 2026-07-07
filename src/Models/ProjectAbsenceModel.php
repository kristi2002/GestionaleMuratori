<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Absence-by-default site attendance: only absences are stored; every other
 * day counts as present ("Lavorato") for the workers assigned to the project.
 */
final class ProjectAbsenceModel
{
    /**
     * Absences of a project in a date range, as a lookup set.
     *
     * @return array<string,true> keyed by "<user_id>|<Y-m-d>"
     */
    public function forRange(int $projectId, string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT user_id, absence_date FROM project_absences
             WHERE project_id = ? AND absence_date BETWEEN ? AND ?'
        );
        $stmt->execute([$projectId, $from, $to]);

        $set = [];
        foreach ($stmt->fetchAll() as $row) {
            $set[(int) $row['user_id'] . '|' . $row['absence_date']] = true;
        }
        return $set;
    }

    /**
     * Absence dates of a single worker in a range — sent along when a worker
     * is (re-)assigned so the freshly built calendar shows any history.
     *
     * @return array<int,string> Y-m-d dates
     */
    public function datesFor(int $projectId, int $userId, string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT absence_date FROM project_absences
             WHERE project_id = ? AND user_id = ? AND absence_date BETWEEN ? AND ?
             ORDER BY absence_date'
        );
        $stmt->execute([$projectId, $userId, $from, $to]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Flips one day for one worker: present → absent inserts the override,
     * absent → present removes it. Returns the resulting state.
     *
     * @return 'worked'|'absent'
     */
    public function toggle(int $projectId, int $userId, string $date): string
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            'DELETE FROM project_absences WHERE project_id = ? AND user_id = ? AND absence_date = ?'
        );
        $stmt->execute([$projectId, $userId, $date]);
        if ($stmt->rowCount() > 0) {
            return 'worked';
        }

        $pdo->prepare(
            'INSERT INTO project_absences (project_id, user_id, absence_date) VALUES (?, ?, ?)'
        )->execute([$projectId, $userId, $date]);
        return 'absent';
    }
}
