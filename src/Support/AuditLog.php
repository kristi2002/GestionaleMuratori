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

    /**
     * @return array<int,array<string,mixed>> newest first
     * @param  string|null $action  optional exact action filter (e.g. 'updated')
     */
    public static function recent(int $limit, int $offset, ?string $action = null): array
    {
        $where  = ($action !== null && $action !== '') ? ' WHERE action = ?' : '';
        $params = $where !== '' ? [$action] : [];
        $stmt   = Database::pdo()->prepare(
            'SELECT * FROM audit_log' . $where
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset)
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Total rows, optionally narrowed to a single action (matches recent()). */
    public static function count(?string $action = null): int
    {
        if ($action !== null && $action !== '') {
            $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM audit_log WHERE action = ?');
            $stmt->execute([$action]);
            return (int) $stmt->fetchColumn();
        }
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM audit_log')->fetchColumn();
    }

    /**
     * Row counts grouped by action — feeds the filter pills (real per-type totals).
     * @return array<string,int> action => count
     */
    public static function actionCounts(): array
    {
        $out  = [];
        $rows = Database::pdo()->query('SELECT action, COUNT(*) AS c FROM audit_log GROUP BY action')->fetchAll();
        foreach ($rows as $r) {
            $out[(string) $r['action']] = (int) $r['c'];
        }
        return $out;
    }

    /**
     * Cheap header aggregates for the KPI strip (indexed on created_at / grouped).
     * @return array{total:int,today:int,users:int}
     */
    public static function stats(): array
    {
        $pdo = Database::pdo();
        return [
            'total' => (int) $pdo->query('SELECT COUNT(*) FROM audit_log')->fetchColumn(),
            'today' => (int) $pdo->query('SELECT COUNT(*) FROM audit_log WHERE created_at >= CURDATE()')->fetchColumn(),
            'users' => (int) $pdo->query('SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE user_id IS NOT NULL')->fetchColumn(),
        ];
    }
}
