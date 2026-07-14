<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Support\AuditLog;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/** Read-only audit trail (admin): who did what, when. */
final class AuditController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        // Optional action-type filter for the pills; whitelist against the known
        // audit action enum so the SQL only ever sees a valid value.
        $known  = ['created', 'updated', 'deleted', 'activated', 'deactivated', 'reset'];
        $action = (string) $request->input('action', '');
        if (!in_array($action, $known, true)) {
            $action = '';
        }
        $filter = $action !== '' ? $action : null;

        $paginator = Paginator::fromRequest($request, AuditLog::count($filter), 40);

        Response::html(View::render('admin/audit/index', [
            'title'        => Lang::get('admin.audit.title'),
            'entries'      => AuditLog::recent($paginator->perPage, $paginator->offset, $filter),
            'paginator'    => $paginator,
            'actionFilter' => $action,
            'actionOrder'  => $known,
            'actionCounts' => AuditLog::actionCounts(),
            'stats'        => AuditLog::stats(),
        ], 'layout'));
    }
}
