<?php
declare(strict_types=1);

namespace App\Support;

use SessionHandlerInterface;

/**
 * Stores PHP sessions in the `sessions` table (migration 037) instead of the
 * container filesystem, so a redeploy/restart no longer signs everyone out.
 *
 * Every DB operation degrades gracefully: if the table is missing (e.g. the
 * migration has not run yet on a fresh deploy) or the DB briefly errors, reads
 * return "no session" and writes report success rather than crashing the request.
 * The worst case is a user being asked to log in again — never a 500.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $stmt = Database::pdo()->prepare('SELECT payload FROM sessions WHERE id = ?');
            $stmt->execute([$id]);
            $payload = $stmt->fetchColumn();
            return $payload === false ? '' : (string) $payload;
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO sessions (id, payload, last_activity) VALUES (:id, :payload, :ts)
                 ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = VALUES(last_activity)'
            );
            $stmt->execute([':id' => $id, ':payload' => $data, ':ts' => time()]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            Database::pdo()->prepare('DELETE FROM sessions WHERE id = ?')->execute([$id]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function gc(int $maxLifetime): int|false
    {
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM sessions WHERE last_activity < ?');
            $stmt->execute([time() - $maxLifetime]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
