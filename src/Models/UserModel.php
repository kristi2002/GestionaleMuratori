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

    /** Persist a user's keyboard-shortcut overrides (JSON string, or null to reset). */
    public function saveShortcuts(int $id, ?string $json): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET shortcuts = ? WHERE id = ?');
        return $stmt->execute([$json, $id]);
    }

    /**
     * User list for the admin panel. Deliberately excludes password_hash:
     * rows are embedded in the page as JSON for the edit modal.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(string $search = '', string $role = ''): array
    {
        $sql    = 'SELECT u.id, u.name, u.email, u.role, u.client_id, u.subcontractor_id,
                          u.is_active, u.created_at,
                          c.name AS client_name, s.name AS subcontractor_name
                   FROM users u
                   LEFT JOIN clients c ON c.id = u.client_id
                   LEFT JOIN subcontractors s ON s.id = u.subcontractor_id
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

    /**
     * User counts grouped by role, plus a '_total' key. Feeds the admin
     * users KPI row and the role pill filters. Roles with no users are absent
     * (callers default to 0); '_total' is the sum across every role.
     *
     * @return array<string,int> e.g. ['_total' => 150, 'admin' => 3, 'worker' => 98]
     */
    public function countsByRole(): array
    {
        $stmt = Database::pdo()->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role');
        $out  = ['_total' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['role']] = (int) $row['n'];
            $out['_total'] += (int) $row['n'];
        }
        return $out;
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
            'INSERT INTO users (name, job_title, email, phone, hire_date, password_hash, role, client_id, subcontractor_id, is_active)
             VALUES (:name, :job_title, :email, :phone, :hire_date, :hash, :role, :client_id, :subcontractor_id, 1)'
        );
        $stmt->execute([
            ':name'             => $data['name'],
            ':job_title'        => $data['job_title'] ?? null,
            ':email'            => $data['email'],
            ':phone'            => $data['phone'] ?? null,
            ':hire_date'        => $data['hire_date'] ?? null,
            ':hash'             => $data['password_hash'],
            ':role'             => $data['role'],
            ':client_id'        => $data['client_id'],
            ':subcontractor_id' => $data['subcontractor_id'] ?? null,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Master-data update; the password is changed only through updatePassword(). */
    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET name = :name, job_title = :job_title, email = :email,
                phone = :phone, hire_date = :hire_date, role = :role,
                client_id = :client_id, subcontractor_id = :subcontractor_id
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'             => $data['name'],
            ':job_title'        => $data['job_title'] ?? null,
            ':email'            => $data['email'],
            ':phone'            => $data['phone'] ?? null,
            ':hire_date'        => $data['hire_date'] ?? null,
            ':role'             => $data['role'],
            ':client_id'        => $data['client_id'],
            ':subcontractor_id' => $data['subcontractor_id'] ?? null,
            ':id'               => $id,
        ]);
    }

    /** Persist the relative path of a stored avatar image (or null to clear it). */
    public function setAvatarPath(int $id, ?string $path): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
        return $stmt->execute([$path, $id]);
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
