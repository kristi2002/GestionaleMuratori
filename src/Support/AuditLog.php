<?php
declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Append-only audit trail of who did what. record() captures the current user +
 * IP snapshot and never throws — auditing must not break the audited operation.
 */
final class AuditLog
{
    public static function record(string $action, string $entity, ?int $entityId = null, string $summary = ''): void
    {
        try {
            $user = Auth::user();
            $stmt = Database::pdo()->prepare(
                'INSERT INTO audit_log (user_id, user_name, action, entity, entity_id, summary, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'] ?? null,
                $user['name'] ?? null,
                $action,
                $entity,
                $entityId,
                $summary !== '' ? mb_substr($summary, 0, 255) : null,
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ]);
        } catch (Throwable $e) {
            error_log('gm audit-failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array<string,mixed>> newest first */
    public static function recent(int $limit, int $offset): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM audit_log ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function count(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
    }
}
