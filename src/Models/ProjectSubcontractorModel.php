<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * M:N link between projects and subcontractors. Drives both the admin assignment
 * UI and the subcontractor portal's "which projects can I see" ownership guard.
 */
final class ProjectSubcontractorModel
{
    /** Project ids a subcontractor is assigned to. @return array<int,int> */
    public function projectIdsFor(int $subcontractorId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT project_id FROM project_subcontractors WHERE subcontractor_id = ?'
        );
        $stmt->execute([$subcontractorId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * Assigned project ids for several subcontractors in one query (avoids N+1 on
     * the admin list page).
     *
     * @param array<int,int> $subcontractorIds
     * @return array<int,array<int,int>> subcontractor_id => [project ids]
     */
    public function projectIdsForMany(array $subcontractorIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $subcontractorIds)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT subcontractor_id, project_id FROM project_subcontractors
             WHERE subcontractor_id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['subcontractor_id']][] = (int) $row['project_id'];
        }
        return $out;
    }

    /** Full project rows a subcontractor may access. @return array<int,array<string,mixed>> */
    public function projectsFor(int $subcontractorId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS client_name
             FROM project_subcontractors ps
             JOIN projects p ON p.id = ps.project_id
             JOIN clients c ON c.id = p.client_id
             WHERE ps.subcontractor_id = ?
             ORDER BY p.start_date DESC, p.name'
        );
        $stmt->execute([$subcontractorId]);
        return $stmt->fetchAll();
    }

    /** True if the subcontractor is assigned to the project (portal ownership check). */
    public function isAssigned(int $subcontractorId, int $projectId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM project_subcontractors WHERE subcontractor_id = ? AND project_id = ?'
        );
        $stmt->execute([$subcontractorId, $projectId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    /** Subcontractors assigned to a project (admin project view). @return array<int,array<string,mixed>> */
    public function subcontractorsFor(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.* FROM project_subcontractors ps
             JOIN subcontractors s ON s.id = ps.subcontractor_id
             WHERE ps.project_id = ?
             ORDER BY s.name'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** Replace a subcontractor's whole project set with the given ids (admin assignment save). */
    public function syncProjects(int $subcontractorId, array $projectIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare('DELETE FROM project_subcontractors WHERE subcontractor_id = ?');
            $del->execute([$subcontractorId]);

            $ins = $pdo->prepare(
                'INSERT INTO project_subcontractors (project_id, subcontractor_id) VALUES (?, ?)'
            );
            foreach (array_unique(array_map('intval', $projectIds)) as $projectId) {
                if ($projectId > 0) {
                    $ins->execute([$projectId, $subcontractorId]);
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
