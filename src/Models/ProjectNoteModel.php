<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Per-project reminders ("Promemoria") shown on the project detail page. */
final class ProjectNoteModel
{
    /** Open notes first, then by due date (nulls last), newest created last. */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM project_notes
             WHERE project_id = ?
             ORDER BY done ASC, due_date IS NULL, due_date ASC, created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_notes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO project_notes (project_id, body, due_date, created_by)
             VALUES (:project_id, :body, :due_date, :created_by)'
        );
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':body'       => $data['body'],
            ':due_date'   => $data['due_date'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function toggleDone(int $id): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE project_notes SET done = 1 - done WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_notes WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
