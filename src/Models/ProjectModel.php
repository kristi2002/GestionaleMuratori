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
        $sql    = 'SELECT p.*, c.name AS client_name
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

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.*, c.name AS client_name
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

    public function countByStatus(string $status): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE status = ?');
        $stmt->execute([$status]);
        return (int) $stmt->fetchColumn();
    }

    /** Number of interventions linked to this project — used to warn before a cascading delete. */
    public function countInterventions(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM interventions WHERE project_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
