<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Subcontractors (subappaltatori): companies working under the main contractor.
 * Assigned to projects via project_subcontractors (M:N) and given a restricted
 * portal login (role=subcontractor) that only ever sees their assigned projects.
 */
final class SubcontractorModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = '', ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($search);
        $sql = 'SELECT * FROM subcontractors' . $where . ' ORDER BY name';
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
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM subcontractors' . $where);
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
        return [' WHERE name LIKE ? OR vat_or_tax_id LIKE ? OR email LIKE ?', [$like, $like, $like]];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM subcontractors WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List-page KPI aggregates: total companies, how many are active, and how many
     * are actually assigned to at least one project (working on a site).
     *
     * @return array{total:int,active:int,on_sites:int}
     */
    public function stats(): array
    {
        $pdo = Database::pdo();
        $total  = (int) $pdo->query('SELECT COUNT(*) FROM subcontractors')->fetchColumn();
        $active = (int) $pdo->query('SELECT COUNT(*) FROM subcontractors WHERE is_active = 1')->fetchColumn();
        $onSites = (int) $pdo->query(
            'SELECT COUNT(DISTINCT subcontractor_id) FROM project_subcontractors'
        )->fetchColumn();

        return ['total' => $total, 'active' => $active, 'on_sites' => $onSites];
    }

    /** Active subcontractors — for assignment dropdowns and the user link field. */
    public function listActive(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM subcontractors WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO subcontractors (name, vat_or_tax_id, email, phone, notes, hourly_rate, is_active)
             VALUES (:name, :vat, :email, :phone, :notes, :hourly_rate, 1)'
        );
        $stmt->execute([
            ':name'        => $data['name'],
            ':vat'         => $data['vat_or_tax_id'],
            ':email'       => $data['email'],
            ':phone'       => $data['phone'],
            ':notes'       => $data['notes'],
            ':hourly_rate' => $data['hourly_rate'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE subcontractors SET name = :name, vat_or_tax_id = :vat, email = :email,
                phone = :phone, notes = :notes, hourly_rate = :hourly_rate
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'        => $data['name'],
            ':vat'         => $data['vat_or_tax_id'],
            ':email'       => $data['email'],
            ':phone'       => $data['phone'],
            ':notes'       => $data['notes'],
            ':hourly_rate' => $data['hourly_rate'] ?? null,
            ':id'          => $id,
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE subcontractors SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }
}
