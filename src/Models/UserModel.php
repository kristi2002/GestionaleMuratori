<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class UserModel
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updatePassword(int $id, string $hash): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        return $stmt->execute([$hash, $id]);
    }

    /**
     * User list for the admin panel. Deliberately excludes password_hash:
     * rows are embedded in the page as JSON for the edit modal.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $search = '', string $role = ''): array
    {
        $sql    = 'SELECT u.id, u.name, u.email, u.role, u.client_id, u.is_active, u.created_at,
                          c.name AS client_name
                   FROM users u
                   LEFT JOIN clients c ON c.id = u.client_id
                   WHERE 1 = 1';
        $params = [];

        if ($search !== '') {
            $sql     .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
            $like     = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($role !== '') {
            $sql      .= ' AND u.role = ?';
            $params[]  = $role;
        }
        $sql .= ' ORDER BY u.name';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        if ($excludeId !== null) {
            $sql      .= ' AND id != ?';
            $params[]  = $excludeId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (name, email, password_hash, role, client_id, is_active)
             VALUES (:name, :email, :hash, :role, :client_id, 1)'
        );
        $stmt->execute([
            ':name'      => $data['name'],
            ':email'     => $data['email'],
            ':hash'      => $data['password_hash'],
            ':role'      => $data['role'],
            ':client_id' => $data['client_id'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Master-data update; the password is changed only through updatePassword(). */
    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET name = :name, email = :email, role = :role, client_id = :client_id
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'      => $data['name'],
            ':email'     => $data['email'],
            ':role'      => $data['role'],
            ':client_id' => $data['client_id'],
            ':id'        => $id,
        ]);
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET is_active = ? WHERE id = ?');
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    /** Active users for a given role — e.g. the worker dropdown on intervention assignment. */
    public function listByRole(string $role, bool $activeOnly = true): array
    {
        $sql    = 'SELECT * FROM users WHERE role = ?';
        $params = [$role];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY name';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
