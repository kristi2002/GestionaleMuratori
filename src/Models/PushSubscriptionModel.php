<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Web Push subscriptions (one per opted-in browser/device). Upsert on the unique
 * endpoint so re-subscribing the same device refreshes its keys instead of
 * duplicating; expired endpoints (404/410 from the push service) are pruned by
 * App\Services\PushService when a send is rejected.
 */
final class PushSubscriptionModel
{
    /** Insert or refresh a subscription, keyed by its unique endpoint. */
    public function upsert(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, last_used_at)
             VALUES (:user_id, :endpoint, :p256dh, :auth, :ua, NOW())
             ON DUPLICATE KEY UPDATE
                 user_id = VALUES(user_id), p256dh = VALUES(p256dh),
                 auth = VALUES(auth), user_agent = VALUES(user_agent), last_used_at = NOW()'
        );
        $stmt->execute([
            ':user_id'  => $userId,
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
            ':ua'       => $userAgent,
        ]);
    }

    /** @return array<int,string> endpoints the user is subscribed on */
    public function endpointsForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare('SELECT endpoint FROM push_subscriptions WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN, 0));
    }

    /** Remove one of the caller's own subscriptions (explicit unsubscribe). */
    public function deleteForUser(int $userId, string $endpoint): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
        $stmt->execute([$userId, $endpoint]);
        return $stmt->rowCount() > 0;
    }

    /** Remove a dead endpoint (push service returned 404/410 Gone) for any user. */
    public function deleteByEndpoint(string $endpoint): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM push_subscriptions WHERE endpoint = ?');
        $stmt->execute([$endpoint]);
    }
}
