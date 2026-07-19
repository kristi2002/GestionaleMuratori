<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\NotificationModel;
use App\Models\PushSubscriptionModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\WebPush;

/**
 * Web Push subscription + content endpoints, open to any authenticated user (each
 * role manages its own devices). The service worker POSTs its subscription here on
 * opt-in and, on receiving a contentless push, fetches the latest notification from
 * /push/pending — resolved against the caller's own feed (admins read the global
 * feed, everyone else their user-scoped feed).
 */
final class PushController
{
    /** GET /push/public-key — the VAPID application-server key for pushManager.subscribe(). */
    public function publicKey(Request $request): void
    {
        AuthGuard::require($request);
        Response::ok(['enabled' => WebPush::isEnabled(), 'key' => WebPush::publicKey()]);
    }

    /** POST /push/subscribe — store (or refresh) the caller's push subscription. */
    public function subscribe(Request $request): void
    {
        $user     = AuthGuard::require($request);
        $endpoint = trim((string) $request->input('endpoint', ''));
        $p256dh   = trim((string) $request->input('p256dh', ''));
        $auth     = trim((string) $request->input('auth', ''));

        if (!$this->validEndpoint($endpoint) || $p256dh === '' || $auth === ''
            || strlen($p256dh) > 255 || strlen($auth) > 255) {
            Response::fail(Lang::get('push.invalid_subscription'), 422);
            return;
        }

        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        (new PushSubscriptionModel())->upsert((int) $user['id'], $endpoint, $p256dh, $auth, $ua !== '' ? $ua : null);
        Response::ok();
    }

    /** POST /push/unsubscribe — drop one of the caller's subscriptions. */
    public function unsubscribe(Request $request): void
    {
        $user     = AuthGuard::require($request);
        $endpoint = trim((string) $request->input('endpoint', ''));
        if ($endpoint === '') {
            Response::fail(Lang::get('push.invalid_subscription'), 422);
            return;
        }
        (new PushSubscriptionModel())->deleteForUser((int) $user['id'], $endpoint);
        Response::ok();
    }

    /** GET /push/pending — latest notification for the caller (service-worker push content). */
    public function pending(Request $request): void
    {
        $user   = AuthGuard::require($request);
        $userId = $user['role'] === 'admin' ? null : (int) $user['id'];
        $recent = (new NotificationModel())->recent(1, $userId);
        if ($recent === []) {
            Response::ok(null);
            return;
        }
        $n = $recent[0];
        Response::ok([
            'title' => (string) $n['title'],
            'body'  => $n['body'] !== null ? (string) $n['body'] : '',
            'url'   => $n['link'] !== null ? (string) $n['link'] : null,
            'tag'   => 'ntf-' . $n['id'],
        ]);
    }

    private function validEndpoint(string $endpoint): bool
    {
        return $endpoint !== ''
            && strlen($endpoint) <= 500
            && filter_var($endpoint, FILTER_VALIDATE_URL) !== false
            && str_starts_with($endpoint, 'https://');
    }
}
