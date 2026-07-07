<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ProjectInvoiceModel
{
    /** @return array<int,array<string,mixed>> Invoices of a project, newest first. */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, u.name AS created_by_name
             FROM project_invoices i JOIN users u ON u.id = i.created_by
             WHERE i.project_id = ?
             ORDER BY i.issue_date DESC, i.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array{search?:string,status?:string,project_id?:int} $filters
     * @return array<int,array<string,mixed>> All invoices with project/client names ("Fatture" list page).
     */
    public function all(array $filters = []): array
    {
        $sql = 'SELECT i.*, p.name AS project_name, c.name AS client_name
                FROM project_invoices i
                JOIN projects p ON p.id = i.project_id
                JOIN clients c ON c.id = p.client_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql     .= ' AND (i.number LIKE ? OR p.name LIKE ? OR c.name LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['status'])) {
            $sql     .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['project_id'])) {
            $sql     .= ' AND i.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        $sql .= ' ORDER BY i.issue_date DESC, i.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Invoice with project + client details — the printable receipt needs both. */
    public function findWithDetails(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, p.name AS project_name, p.location AS project_location,
                    c.name AS client_name, c.address AS client_address,
                    c.vat_or_tax_id AS client_vat, c.email AS client_email,
                    u.name AS created_by_name
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN users u ON u.id = i.created_by
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE project_invoices SET project_id = :project_id, number = :number,
                issue_date = :issue_date, amount = :amount, status = :status, note = :note
             WHERE id = :id'
        );
        return $stmt->execute([
            ':project_id' => $data['project_id'],
            ':number'     => $data['number'],
            ':issue_date' => $data['issue_date'],
            ':amount'     => $data['amount'],
            ':status'     => $data['status'],
            ':note'       => $data['note'],
            ':id'         => $id,
        ]);
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO project_invoices (project_id, number, issue_date, amount, status, note, created_by)
             VALUES (:project_id, :number, :issue_date, :amount, :status, :note, :created_by)'
        );
        $stmt->execute([
            ':project_id' => $data['project_id'],
            ':number'     => $data['number'],
            ':issue_date' => $data['issue_date'],
            ':amount'     => $data['amount'],
            ':status'     => $data['status'],
            ':note'       => $data['note'],
            ':created_by' => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_invoices WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Suggested next number for the create form, e.g. "2026/003".
     * Based on the highest suffix already issued this year (not a row count),
     * so deleting an invoice can never cause a number to be re-used.
     */
    public function nextNumberSuggestion(): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED))
             FROM project_invoices WHERE number LIKE CONCAT(YEAR(CURDATE()), '/%')"
        );
        $stmt->execute();
        return date('Y') . '/' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }
}
