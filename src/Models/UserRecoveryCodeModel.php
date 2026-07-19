<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * One-time two-factor recovery codes (sha256-hashed at rest). Shown once on 2FA
 * enable; each can be used once to log in when the authenticator is unavailable.
 */
final class UserRecoveryCodeModel
{
    /** Normalise a code for hashing/lookup (case-insensitive, ignore separators). */
    public static function normalize(string $code): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $code));
    }

    public static function hash(string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /** Replace all of a user's codes with fresh hashes. @param array<int,string> $hashes */
    public function replaceForUser(int $userId, array $hashes): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM user_recovery_codes WHERE user_id = ?')->execute([$userId]);
        $ins = $pdo->prepare('INSERT INTO user_recovery_codes (user_id, code_hash) VALUES (?, ?)');
        foreach ($hashes as $hash) {
            $ins->execute([$userId, $hash]);
        }
    }

    /** Consume one matching unused code; true if one was found and marked used. */
    public function consume(int $userId, string $code): bool
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM user_recovery_codes WHERE user_id = ? AND code_hash = ? AND used_at IS NULL LIMIT 1'
        );
        $stmt->execute([$userId, self::hash($code)]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return false;
        }
        $pdo->prepare('UPDATE user_recovery_codes SET used_at = NOW() WHERE id = ?')->execute([$id]);
        return true;
    }

    public function countUnused(int $userId): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND used_at IS NULL');
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteForUser(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM user_recovery_codes WHERE user_id = ?')->execute([$userId]);
    }
}
