<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ClientModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = '', ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($search);
        // Per-client project count and total invoiced amount (real KPI data for
        // the card grid). Correlated subqueries keep the single-statement shape.
        $sql = 'SELECT clients.*,
                (SELECT COUNT(*) FROM projects p WHERE p.client_id = clients.id) AS project_count,
                (SELECT COALESCE(SUM(pi.amount), 0) FROM project_invoices pi
                    JOIN projects p2 ON p2.id = pi.project_id
                    WHERE p2.client_id = clients.id) AS invoiced_total
            FROM clients' . $where . ' ORDER BY name';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Row count for the same search (drives pagination). */
    public function count(string $search = ''): int
    {
        [$where, $params] = $this->filterSql($search);
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM clients' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function filterSql(string $search): array
    {
        if ($search === '') {
            return ['', []];
        }
        $like = '%' . $search . '%';
        return [' WHERE name LIKE ? OR vat_or_tax_id LIKE ?', [$like, $like]];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO clients (name, vat_or_tax_id, email, phone, address, notes)
             VALUES (:name, :vat, :email, :phone, :address, :notes)'
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':vat'     => $data['vat_or_tax_id'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':notes'   => $data['notes'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE clients SET name = :name, vat_or_tax_id = :vat, email = :email,
                phone = :phone, address = :address, notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'    => $data['name'],
            ':vat'     => $data['vat_or_tax_id'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':notes'   => $data['notes'],
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM clients WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Total number of projects across every client (KPI header). */
    public function totalProjects(): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Sum of all invoiced amounts across every project (KPI header). */
    public function totalInvoiced(): float
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount), 0) FROM project_invoices');
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    /** Number of projects linked to this client — used to warn before a cascading delete. */
    public function countProjects(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
