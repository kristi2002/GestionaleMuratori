<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Models\NotificationModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Client-facing notification feed. Every query is scoped to the logged-in client
 * user's own id (NotificationModel's user_id filter), so a client can only ever see
 * or mark their own rows — never the admin/global feed or another client's.
 */
final class NotificationController
{
    /** GET /client/notifications — the client's own feed (all or ?filter=unread). */
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['client']);

        $uid        = (int) Auth::id();
        $model      = new NotificationModel();
        $unreadOnly = (string) $request->input('filter', '') === 'unread';
        $paginator  = Paginator::fromRequest($request, $model->countAll($unreadOnly, $uid), 30);

        Response::html(View::render('client/notifications/index', [
            'title'         => Lang::get('notifications.title'),
            'notifications' => $model->all($unreadOnly, $uid, $paginator->perPage, $paginator->offset),
            'unreadOnly'    => $unreadOnly,
            'unreadCount'   => $model->unreadCount($uid),
            'paginator'     => $paginator,
        ], 'layout'));
    }

    /** POST /client/notifications/{id}/read — mark one of the client's own read. */
    public function read(Request $request, string $id): void
    {
        AuthGuard::require($request, ['client']);
        (new NotificationModel())->markRead((int) $id, (int) Auth::id());
        Response::ok();
    }

    /** POST /client/notifications/read-all — mark all of the client's own read. */
    public function readAll(Request $request): void
    {
        AuthGuard::require($request, ['client']);
        Response::ok(['count' => (new NotificationModel())->markAllRead((int) Auth::id())]);
    }
}
