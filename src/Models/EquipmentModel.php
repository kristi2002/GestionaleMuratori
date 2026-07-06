<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Equipment catalog (mezzi/attrezzature) attached to Giornale dei Lavori entries.
 */
final class EquipmentModel
{
    /** @return array<int,array<string,mixed>> */
    public function listActive(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM equipment WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM equipment WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name): int
    {
        $stmt = Database::pdo()->prepare('INSERT INTO equipment (name, is_active) VALUES (?, 1)');
        $stmt->execute([$name]);
        return (int) Database::pdo()->lastInsertId();
    }
}
