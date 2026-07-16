<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Suppliers (fornitori): companies that sell materials to the firm. The counterparty
 * of a purchase order (buono d'ordine). Kept separate from subcontractors, who do work
 * on site and have a portal login — a supplier is a vendor only.
 */
final class SupplierModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = '', ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($search);
        $sql = 'SELECT * FROM suppliers' . $where . ' ORDER BY name';
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
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM suppliers' . $where);
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
        $stmt = Database::pdo()->prepare('SELECT * FROM suppliers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List-page KPI aggregates: total suppliers, how many are active, and how many
     * have at least one purchase order on record.
     *
     * @return array{total:int,active:int,with_orders:int}
     */
    public function stats(): array
    {
        $pdo = Database::pdo();
        return [
            'total'       => (int) $pdo->query('SELECT COUNT(*) FROM suppliers')->fetchColumn(),
            'active'      => (int) $pdo->query('SELECT COUNT(*) FROM suppliers WHERE is_active = 1')->fetchColumn(),
            'with_orders' => (int) $pdo->query('SELECT COUNT(DISTINCT supplier_id) FROM purchase_orders')->fetchColumn(),
        ];
    }

    /** Active suppliers — for the purchase-order supplier dropdown. */
    public function listActive(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM suppliers WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO suppliers (name, vat_or_tax_id, email, phone, address, notes, is_active)
             VALUES (:name, :vat, :email, :phone, :address, :notes, 1)'
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
            'UPDATE suppliers SET name = :name, vat_or_tax_id = :vat, email = :email,
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

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE suppliers SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }
}
