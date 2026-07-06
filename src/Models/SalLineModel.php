<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Priced line items of an S.A.L. document. amount = qty × unit_price, computed by
 * the controller and stored so an issued document's figures are frozen.
 */
final class SalLineModel
{
    /** @return array<int,array<string,mixed>> */
    public function forDocument(int $salId): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sal_lines WHERE sal_id = ? ORDER BY id');
        $stmt->execute([$salId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM sal_lines WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sal_lines (sal_id, description, qty, unit, unit_price, amount)
             VALUES (:sal_id, :description, :qty, :unit, :unit_price, :amount)'
        );
        $stmt->execute([
            ':sal_id'      => $data['sal_id'],
            ':description' => $data['description'],
            ':qty'         => $data['qty'],
            ':unit'        => $data['unit'],
            ':unit_price'  => $data['unit_price'],
            ':amount'      => $data['amount'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM sal_lines WHERE id = ?');
        return $stmt->execute([$id]);
    }
}
