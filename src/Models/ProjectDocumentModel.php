<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ProjectDocumentModel
{
    /** @return array<int,array<string,mixed>> Documents of a project, newest first. */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT d.*, u.name AS uploaded_by_name
             FROM project_documents d JOIN users u ON u.id = d.uploaded_by
             WHERE d.project_id = ?
             ORDER BY d.created_at DESC, d.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_documents WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO project_documents (project_id, title, original_name, file_path, mime_type, size_bytes, uploaded_by)
             VALUES (:project_id, :title, :original_name, :file_path, :mime_type, :size_bytes, :uploaded_by)'
        );
        $stmt->execute([
            ':project_id'    => $data['project_id'],
            ':title'         => $data['title'],
            ':original_name' => $data['original_name'],
            ':file_path'     => $data['file_path'],
            ':mime_type'     => $data['mime_type'],
            ':size_bytes'    => $data['size_bytes'],
            ':uploaded_by'   => $data['uploaded_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_documents WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
