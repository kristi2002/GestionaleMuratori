<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ClientModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = ''): array
    {
        if ($search !== '') {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM clients WHERE name LIKE ? OR vat_or_tax_id LIKE ? ORDER BY name'
            );
            $like = '%' . $search . '%';
            $stmt->execute([$like, $like]);
        } else {
            $stmt = Database::pdo()->query('SELECT * FROM clients ORDER BY name');
        }
        return $stmt->fetchAll();
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

    /** Number of projects linked to this client — used to warn before a cascading delete. */
    public function countProjects(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }
}
