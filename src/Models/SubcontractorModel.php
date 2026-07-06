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
    public function all(string $search = ''): array
    {
        $sql    = 'SELECT * FROM subcontractors WHERE 1 = 1';
        $params = [];
        if ($search !== '') {
            $sql     .= ' AND (name LIKE ? OR vat_or_tax_id LIKE ? OR email LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY name';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM subcontractors WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
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
            'INSERT INTO subcontractors (name, vat_or_tax_id, email, phone, notes, is_active)
             VALUES (:name, :vat, :email, :phone, :notes, 1)'
        );
        $stmt->execute([
            ':name'  => $data['name'],
            ':vat'   => $data['vat_or_tax_id'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':notes' => $data['notes'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE subcontractors SET name = :name, vat_or_tax_id = :vat, email = :email,
                phone = :phone, notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'  => $data['name'],
            ':vat'   => $data['vat_or_tax_id'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':notes' => $data['notes'],
            ':id'    => $id,
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE subcontractors SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }
}
