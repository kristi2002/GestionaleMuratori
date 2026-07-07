<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ExpenseModel
{
    /**
     * @param array{search?:string,category?:string,worker_id?:int,project_id?:int,date_from?:string,date_to?:string} $filters
     * @return array<int,array<string,mixed>> Expenses with worker/project names, newest first.
     */
    public function all(array $filters = []): array
    {
        [$where, $params] = $this->whereClause($filters);
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, w.name AS worker_name, p.name AS project_name, u.name AS created_by_name
             FROM expenses e
             LEFT JOIN users w ON w.id = e.worker_id
             LEFT JOIN projects p ON p.id = e.project_id
             JOIN users u ON u.id = e.created_by'
            . $where .
            ' ORDER BY e.expense_date DESC, e.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Totals of the filtered rows, per category plus overall.
     * @return array{by_category: array<string,string>, total: string}
     */
    public function totals(array $filters = []): array
    {
        [$where, $params] = $this->whereClause($filters);
        $stmt = Database::pdo()->prepare(
            'SELECT e.category, SUM(e.amount) AS total
             FROM expenses e
             LEFT JOIN users w ON w.id = e.worker_id
             LEFT JOIN projects p ON p.id = e.project_id'
            . $where .
            ' GROUP BY e.category'
        );
        $stmt->execute($params);

        $byCategory = [];
        $total      = 0.0;
        foreach ($stmt->fetchAll() as $row) {
            $byCategory[(string) $row['category']] = (string) $row['total'];
            $total += (float) $row['total'];
        }
        return ['by_category' => $byCategory, 'total' => number_format($total, 2, '.', '')];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM expenses WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO expenses (expense_date, category, description, amount, worker_id, project_id, note, created_by)
             VALUES (:expense_date, :category, :description, :amount, :worker_id, :project_id, :note, :created_by)'
        );
        $stmt->execute([
            ':expense_date' => $data['expense_date'],
            ':category'     => $data['category'],
            ':description'  => $data['description'],
            ':amount'       => $data['amount'],
            ':worker_id'    => $data['worker_id'],
            ':project_id'   => $data['project_id'],
            ':note'         => $data['note'],
            ':created_by'   => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE expenses SET expense_date = :expense_date, category = :category,
                description = :description, amount = :amount, worker_id = :worker_id,
                project_id = :project_id, note = :note
             WHERE id = :id'
        );
        return $stmt->execute([
            ':expense_date' => $data['expense_date'],
            ':category'     => $data['category'],
            ':description'  => $data['description'],
            ':amount'       => $data['amount'],
            ':worker_id'    => $data['worker_id'],
            ':project_id'   => $data['project_id'],
            ':note'         => $data['note'],
            ':id'           => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM expenses WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @return array{0:string,1:array<int,mixed>} Shared WHERE builder for all() and totals(). */
    private function whereClause(array $filters): array
    {
        $where  = ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['search'])) {
            $where   .= ' AND (e.description LIKE ? OR e.note LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['category'])) {
            $where   .= ' AND e.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['worker_id'])) {
            $where   .= ' AND e.worker_id = ?';
            $params[] = (int) $filters['worker_id'];
        }
        if (!empty($filters['project_id'])) {
            $where   .= ' AND e.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        if (!empty($filters['date_from'])) {
            $where   .= ' AND e.expense_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where   .= ' AND e.expense_date <= ?';
            $params[] = $filters['date_to'];
        }
        return [$where, $params];
    }
}
