<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class PhotoModel
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO photos
                (intervention_id, project_id, type, file_path, thumb_path, uploaded_by, lat, lng, captured_at)
             VALUES
                (:intervention_id, :project_id, :type, :file_path, :thumb_path, :uploaded_by, :lat, :lng, :captured_at)'
        );
        $stmt->execute([
            ':intervention_id' => $data['intervention_id'],
            ':project_id'      => $data['project_id'],
            ':type'            => $data['type'],
            ':file_path'       => $data['file_path'],
            ':thumb_path'      => $data['thumb_path'],
            ':uploaded_by'     => $data['uploaded_by'],
            ':lat'             => $data['lat'] ?? null,
            ':lng'             => $data['lng'] ?? null,
            ':captured_at'     => $data['captured_at'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM photos WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function forIntervention(int $interventionId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM photos WHERE intervention_id = ? ORDER BY type, created_at'
        );
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll();
    }

    /** All photos on a project (across its interventions), newest first, capped. */
    public function forProject(int $projectId, int $limit = 120): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, i.title AS intervention_title
             FROM photos p
             LEFT JOIN interventions i ON i.id = p.intervention_id
             WHERE p.project_id = ?
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** §4.4 completion gate — at least one 'after' photo must exist. */
    public function hasAfterPhoto(int $interventionId): bool
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM photos WHERE intervention_id = ? AND type = 'after'"
        );
        $stmt->execute([$interventionId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}
