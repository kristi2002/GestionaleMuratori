<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class PhotoModel
{
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO photos (intervention_id, project_id, type, file_path, thumb_path, uploaded_by)
             VALUES (:intervention_id, :project_id, :type, :file_path, :thumb_path, :uploaded_by)'
        );
        $stmt->execute([
            ':intervention_id' => $data['intervention_id'],
            ':project_id'      => $data['project_id'],
            ':type'            => $data['type'],
            ':file_path'       => $data['file_path'],
            ':thumb_path'      => $data['thumb_path'],
            ':uploaded_by'     => $data['uploaded_by'],
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
