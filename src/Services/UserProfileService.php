<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

/**
 * Aggregates the read-only panels of the admin operaio/user profile page from
 * existing operational data: site attendance (hours + worked days + current
 * cantiere), assigned interventions, and personal compliance documents.
 */
final class UserProfileService
{
    /** @return array<string,mixed> */
    public function forUser(int $userId): array
    {
        return [
            'stats'         => $this->stats($userId),
            'attendance'    => $this->attendanceDays($userId),
            'interventions' => $this->interventions($userId),
            'documents'     => $this->documents($userId),
        ];
    }

    /** Hours + days worked this month, current cantiere, and intervention counts. */
    private function stats(int $userId): array
    {
        $pdo = Database::pdo();

        $month = $this->one(
            $pdo,
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, entry_at, exit_at)), 0) / 60 AS hours_month,
                    COUNT(DISTINCT DATE(entry_at)) AS days_month
             FROM site_attendance
             WHERE user_id = ? AND exit_at IS NOT NULL
               AND YEAR(entry_at) = YEAR(CURDATE()) AND MONTH(entry_at) = MONTH(CURDATE())",
            [$userId]
        );

        $site = $this->one(
            $pdo,
            "SELECT p.name
             FROM site_attendance a JOIN projects p ON p.id = a.project_id
             WHERE a.user_id = ?
             ORDER BY a.entry_at DESC LIMIT 1",
            [$userId]
        );

        return [
            'hours_month'  => (float) ($month['hours_month'] ?? 0),
            'days_month'   => (int) ($month['days_month'] ?? 0),
            'current_site' => $site['name'] ?? null,
            'assigned'     => (int) $this->scalar($pdo, "SELECT COUNT(*) FROM interventions WHERE assigned_worker_id = ?", [$userId]),
            'completed'    => (int) $this->scalar($pdo, "SELECT COUNT(*) FROM interventions WHERE assigned_worker_id = ? AND status = 'completed'", [$userId]),
        ];
    }

    /** @return array<int,string> distinct worked 'Y-m-d' dates in the current month */
    private function attendanceDays(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DISTINCT DATE(entry_at) AS d
             FROM site_attendance
             WHERE user_id = ?
               AND YEAR(entry_at) = YEAR(CURDATE()) AND MONTH(entry_at) = MONTH(CURDATE())"
        );
        $stmt->execute([$userId]);
        return array_map(static fn (array $r): string => (string) $r['d'], $stmt->fetchAll());
    }

    /** @return array<int,array<string,mixed>> recent interventions assigned to the worker */
    private function interventions(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.title, i.status, i.scheduled_date, p.name AS project_name
             FROM interventions i JOIN projects p ON p.id = i.project_id
             WHERE i.assigned_worker_id = ?
             ORDER BY COALESCE(i.scheduled_date, i.created_at) DESC
             LIMIT 8"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> compliance documents for this worker */
    private function documents(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id, doc_type, issue_date, expiry_date, file_path
             FROM compliance_documents
             WHERE subject_type = 'worker' AND subject_id = ?
             ORDER BY (expiry_date IS NULL) ASC, expiry_date ASC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,mixed> $params */
    private function one(PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    /** @param array<int,mixed> $params */
    private function scalar(PDO $pdo, string $sql, array $params): mixed
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
