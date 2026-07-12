<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Single-use, time-limited password-reset tokens (only the SHA-256 hash is stored). */
final class PasswordResetModel
{
    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $tokenHash, $expiresAt]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Invalidate any pending tokens for a user before issuing a new one. */
    public function deleteForUser(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
    }

    /** A usable token: matching hash, not used, not expired. */
    public function findValid(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        Database::pdo()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$id]);
    }
}
