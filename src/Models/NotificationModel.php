<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * In-app notification feed (admin-facing). Rows are created by the scheduler
 * (see App\Services\SchedulerService) via createIfAbsent(), which is idempotent
 * on dedup_key so a daily run never duplicates an existing alert.
 */
final class NotificationModel
{
    /**
     * Insert a notification unless one with the same dedup_key already exists.
     * Returns true when a new row was created, false when it was a duplicate.
     * A null dedup_key is never de-duplicated (always inserted).
     *
     * @param array{type:string,severity?:string,title:string,body?:?string,link?:?string,dedup_key?:?string} $data
     */
    public function createIfAbsent(array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO notifications (type, severity, title, body, link, dedup_key)
             VALUES (:type, :severity, :title, :body, :link, :dedup_key)'
        );
        $stmt->execute([
            ':type'      => $data['type'],
            ':severity'  => $data['severity'] ?? 'info',
            ':title'     => $data['title'],
            ':body'      => $data['body'] ?? null,
            ':link'      => $data['link'] ?? null,
            ':dedup_key' => $data['dedup_key'] ?? null,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function unreadCount(): int
    {
        return (int) Database::pdo()->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        $stmt  = Database::pdo()->query(
            'SELECT * FROM notifications ORDER BY is_read ASC, created_at DESC, id DESC LIMIT ' . $limit
        );
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function all(bool $unreadOnly = false): array
    {
        $sql = 'SELECT * FROM notifications';
        if ($unreadOnly) {
            $sql .= ' WHERE is_read = 0';
        }
        $sql .= ' ORDER BY is_read ASC, created_at DESC, id DESC LIMIT 500';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public function markRead(int $id): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND is_read = 0'
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(): int
    {
        $stmt = Database::pdo()->prepare('UPDATE notifications SET is_read = 1, read_at = NOW() WHERE is_read = 0');
        $stmt->execute();
        return $stmt->rowCount();
    }
}
