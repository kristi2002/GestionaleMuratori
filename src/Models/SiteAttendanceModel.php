<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Badge di Cantiere Digitale (Decreto 332/2026): on-site attendance register.
 * Every entry/exit is a row with a timestamp and optional GPS coordinates, so the
 * firm can prove who was on a reconstruction site and when (anti-undeclared-work).
 *
 * A "subject" is a login user: workers (user_id set, subcontractor_id NULL) and
 * subcontractor logins (user_id set, subcontractor_id = their company). Tracking by
 * user_id gives one uniform "am I clocked in" rule for both roles.
 */
final class SiteAttendanceModel
{
    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM site_attendance WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The user's currently-open attendance (clocked in, not yet out), if any. */
    public function openForUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, p.name AS project_name
             FROM site_attendance a
             JOIN projects p ON p.id = a.project_id
             WHERE a.user_id = ? AND a.exit_at IS NULL
             ORDER BY a.entry_at DESC, a.id DESC LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Clock in: create an open attendance row. Returns the new id. */
    public function clockIn(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO site_attendance
                (project_id, user_id, subcontractor_id, person_name, entry_at, entry_lat, entry_lng, note)
             VALUES
                (:project_id, :user_id, :subcontractor_id, :person_name, :entry_at, :entry_lat, :entry_lng, :note)'
        );
        $stmt->execute([
            ':project_id'       => $data['project_id'],
            ':user_id'          => $data['user_id'],
            ':subcontractor_id' => $data['subcontractor_id'] ?? null,
            ':person_name'      => $data['person_name'],
            ':entry_at'         => $data['entry_at'],
            ':entry_lat'        => $data['entry_lat'] ?? null,
            ':entry_lng'        => $data['entry_lng'] ?? null,
            ':note'             => $data['note'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Clock out: close an open row with the exit time and optional coordinates. */
    public function clockOut(int $id, string $exitAt, ?string $lat, ?string $lng): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE site_attendance SET exit_at = :exit_at, exit_lat = :lat, exit_lng = :lng
             WHERE id = :id AND exit_at IS NULL'
        );
        return $stmt->execute([':exit_at' => $exitAt, ':lat' => $lat, ':lng' => $lng, ':id' => $id]);
    }

    /** Attendance register for a project (optionally a single day), newest first. */
    public function forProject(int $projectId, ?string $date = null): array
    {
        $sql = 'SELECT a.*, s.name AS subcontractor_name
                FROM site_attendance a
                LEFT JOIN subcontractors s ON s.id = a.subcontractor_id
                WHERE a.project_id = ?';
        $params = [$projectId];
        if ($date !== null && $date !== '') {
            $sql     .= ' AND DATE(a.entry_at) = ?';
            $params[] = $date;
        }
        $sql .= ' ORDER BY a.entry_at DESC, a.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Recent attendance for one user (their own history tab). */
    public function recentForUser(int $userId, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, p.name AS project_name
             FROM site_attendance a
             JOIN projects p ON p.id = a.project_id
             WHERE a.user_id = ?
             ORDER BY a.entry_at DESC, a.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Per-day clock-in counts over a date window (by DATE(entry_at)), for the
     * dashboard "presenze" trend sparkline.
     *
     * @return array<string,int> 'Y-m-d' => count (only non-zero days present)
     */
    public function dailyClockIns(string $from, string $to): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT DATE(entry_at) AS d, COUNT(*) AS n FROM site_attendance
             WHERE DATE(entry_at) BETWEEN ? AND ? GROUP BY d'
        );
        $stmt->execute([$from, $to]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['d']] = (int) $row['n'];
        }
        return $out;
    }

    /** Distinct workers currently on site for a project (admin "who's here now"). */
    public function countPresent(int $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM site_attendance WHERE project_id = ? AND exit_at IS NULL'
        );
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }
}
