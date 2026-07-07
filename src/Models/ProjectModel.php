<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ProjectModel
{
    /**
     * @param array{client_id?:int,status?:string,search?:string} $filters
     * @return array<int,array<string,mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql    = 'SELECT p.*, c.name AS client_name,
                        (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR \', \')
                           FROM project_workers pw
                           JOIN users u ON u.id = pw.user_id
                          WHERE pw.project_id = p.id) AS worker_names
                    FROM projects p
                    JOIN clients c ON c.id = p.client_id
                    WHERE 1 = 1';
        $params = [];

        if (!empty($filters['client_id'])) {
            $sql           .= ' AND p.client_id = ?';
            $params[]       = (int) $filters['client_id'];
        }
        if (!empty($filters['status'])) {
            $sql           .= ' AND p.status = ?';
            $params[]       = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql           .= ' AND (p.name LIKE ? OR p.location LIKE ?)';
            $like           = '%' . $filters['search'] . '%';
            $params[]       = $like;
            $params[]       = $like;
        }
        $sql .= ' ORDER BY p.start_date DESC, p.name';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Number of projects in the given status (dashboard KPI). */
    public function countByStatus(string $status): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS client_name,
                    (SELECT GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR \', \')
                       FROM project_workers pw
                       JOIN users u ON u.id = pw.user_id
                      WHERE pw.project_id = p.id) AS worker_names
             FROM projects p JOIN clients c ON c.id = p.client_id
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO projects (client_id, name, location, start_date, end_date, invoice_reference, status)
             VALUES (:client_id, :name, :location, :start_date, :end_date, :invoice_reference, :status)'
        );
        $stmt->execute([
            ':client_id'         => $data['client_id'],
            ':name'              => $data['name'],
            ':location'          => $data['location'],
            ':start_date'        => $data['start_date'],
            ':end_date'          => $data['end_date'],
            ':invoice_reference' => $data['invoice_reference'],
            ':status'            => $data['status'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE projects SET client_id = :client_id, name = :name, location = :location,
                start_date = :start_date, end_date = :end_date,
                invoice_reference = :invoice_reference, status = :status
             WHERE id = :id'
        );
        return $stmt->execute([
            ':client_id'         => $data['client_id'],
            ':name'              => $data['name'],
            ':location'          => $data['location'],
            ':start_date'        => $data['start_date'],
            ':end_date'          => $data['end_date'],
            ':invoice_reference' => $data['invoice_reference'],
            ':status'            => $data['status'],
            ':id'                => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @return array<int,array<string,mixed>> Projects a worker is assigned to (profile page). */
    public function forWorker(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS client_name
             FROM projects p
             JOIN clients c ON c.id = p.client_id
             JOIN project_workers pw ON pw.project_id = p.id
             WHERE pw.user_id = ?
             ORDER BY p.start_date DESC, p.name'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> Assigned workers (id, name), alphabetical — the attendance roster. */
    public function workers(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id, u.name
             FROM project_workers pw JOIN users u ON u.id = pw.user_id
             WHERE pw.project_id = ?
             ORDER BY u.name'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,int> IDs of the workers assigned to this project. */
    public function workerIds(int $projectId): array
    {
        $stmt = Database::pdo()->prepare('SELECT user_id FROM project_workers WHERE project_id = ?');
        $stmt->execute([$projectId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Links one worker to the project (attendance register "+ Assegna operaio"). */
    public function addWorker(int $projectId, int $userId): void
    {
        Database::pdo()
            ->prepare('INSERT INTO project_workers (project_id, user_id) VALUES (?, ?)')
            ->execute([$projectId, $userId]);
    }

    /** Unlinks one worker from the project. Logged absences are kept as history. */
    public function removeWorker(int $projectId, int $userId): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_workers WHERE project_id = ? AND user_id = ?');
        $stmt->execute([$projectId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Replaces the project's worker assignments with the given user IDs. */
    public function syncWorkers(int $projectId, array $userIds): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('DELETE FROM project_workers WHERE project_id = ?');
            $stmt->execute([$projectId]);

            $insert = $pdo->prepare('INSERT INTO project_workers (project_id, user_id) VALUES (?, ?)');
            foreach (array_unique(array_map('intval', $userIds)) as $userId) {
                $insert->execute([$projectId, $userId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Number of interventions linked to this project — used to warn before a cascading delete. */
    public function countInterventions(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM interventions WHERE project_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
