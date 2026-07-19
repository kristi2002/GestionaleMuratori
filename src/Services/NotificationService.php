<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\UserModel;

/**
 * Fan-out helper for user-scoped in-app notifications. The scheduler writes the
 * admin/global feed directly (user_id NULL); this service targets the portal users
 * of a client company so client-facing events (quote sent, invoice issued) also
 * surface in the bell, alongside the transactional e-mail (App\Services\MailService).
 */
final class NotificationService
{
    /**
     * Create one notification per active portal user of a client company. The
     * dedup_key is suffixed with the user id so the globally-UNIQUE dedup constraint
     * still de-duplicates per recipient (re-emitting the same event is a no-op).
     * Best-effort: returns how many rows were created.
     *
     * @param array{type:string,severity?:string,title:string,body?:?string,link?:?string,dedup_key:string} $data
     */
    public static function notifyClient(int $clientId, array $data): int
    {
        if ($clientId <= 0) {
            return 0;
        }
        $model   = new NotificationModel();
        $created = 0;
        foreach ((new UserModel())->clientUserIds($clientId) as $userId) {
            $row              = $data;
            $row['user_id']   = $userId;
            $row['dedup_key'] = $data['dedup_key'] . ':u' . $userId;
            if ($model->createIfAbsent($row)) {
                $created++;
                // Best-effort lock-screen push; no-op when push is unconfigured.
                PushService::sendToUser((int) $userId);
            }
        }
        return $created;
    }

    /**
     * Create one user-scoped notification and push it to that user's devices.
     * Idempotent via dedup_key; returns true when a new row was created (and a push
     * attempted). Best-effort push — a no-op when Web Push is unconfigured.
     *
     * @param array{type:string,severity?:string,title:string,body?:?string,link?:?string,dedup_key:string} $data
     */
    public static function notifyUser(int $userId, array $data): bool
    {
        if ($userId <= 0) {
            return false;
        }
        $data['user_id'] = $userId;
        if (!(new NotificationModel())->createIfAbsent($data)) {
            return false;
        }
        PushService::sendToUser($userId);
        return true;
    }
}
