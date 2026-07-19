<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Public "request a job" leads. Created unauthenticated from /request; worked in the
 * admin inbox through a small status workflow (new → contacted → converted/archived).
 */
final class LeadModel
{
    private const STATUSES = ['new', 'contacted', 'converted', 'archived'];

    /** @param array{name:string,email:?string,phone:?string,message:?string,source:?string,ip:?string} $data */
    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO leads (name, email, phone, message, source, ip)
             VALUES (:name, :email, :phone, :message, :source, :ip)'
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':message' => $data['message'],
            ':source'  => $data['source'],
            ':ip'      => $data['ip'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM leads WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> filtered by status (empty = all), newest first */
    public function all(string $status = '', ?int $limit = null, int $offset = 0): array
    {
        $sql    = 'SELECT l.*, c.name AS client_name FROM leads l
                   LEFT JOIN clients c ON c.id = l.client_id';
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $sql     .= ' WHERE l.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY l.created_at DESC, l.id DESC';
        $sql .= $limit !== null ? ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset) : ' LIMIT 500';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Row count for the same status filter (drives pagination). */
    public function count(string $status = ''): int
    {
        $sql    = 'SELECT COUNT(*) FROM leads';
        $params = [];
        if (in_array($status, self::STATUSES, true)) {
            $sql     .= ' WHERE status = ?';
            $params[] = $status;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string,int> per-status counts + '_total' */
    public function countByStatus(): array
    {
        $out = ['_total' => 0];
        foreach (self::STATUSES as $s) {
            $out[$s] = 0;
        }
        foreach (Database::pdo()->query('SELECT status, COUNT(*) AS n FROM leads GROUP BY status')->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
            $out['_total'] += (int) $row['n'];
        }
        return $out;
    }

    public function newCount(): int
    {
        return (int) Database::pdo()->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUSES, true)) {
            return false;
        }
        $stmt = Database::pdo()->prepare('UPDATE leads SET status = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /** Link a lead to the client it was converted into and mark it converted. */
    public function markConverted(int $id, int $clientId): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE leads SET status = 'converted', client_id = ?, updated_at = NOW() WHERE id = ?"
        );
        return $stmt->execute([$clientId, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM leads WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** @return array<int,string> */
    public static function statuses(): array
    {
        return self::STATUSES;
    }
}
