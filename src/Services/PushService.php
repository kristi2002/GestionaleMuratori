<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PushSubscriptionModel;
use App\Support\WebPush;

/**
 * Fan-out of Web Push tickles to a user's subscribed devices. Best-effort and a
 * no-op when push is unconfigured (WebPush::isEnabled() false), so callers can
 * invoke it unconditionally alongside creating the in-app notification. A push
 * service replying 404/410 means the subscription is gone — we prune it.
 */
final class PushService
{
    /** Push to every device the user is subscribed on. Returns how many were accepted. */
    public static function sendToUser(int $userId): int
    {
        if ($userId <= 0 || !WebPush::isEnabled()) {
            return 0;
        }
        $model = new PushSubscriptionModel();
        $sent  = 0;
        foreach ($model->endpointsForUser($userId) as $endpoint) {
            $status = WebPush::sendTo($endpoint);
            if ($status === 404 || $status === 410) {
                $model->deleteByEndpoint($endpoint);
            } elseif ($status >= 200 && $status < 300) {
                $sent++;
            }
        }
        return $sent;
    }

    /** Push to several users (e.g. every admin for a scheduler alert). */
    public static function sendToUsers(array $userIds): int
    {
        $sent = 0;
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            $sent += self::sendToUser($userId);
        }
        return $sent;
    }
}
