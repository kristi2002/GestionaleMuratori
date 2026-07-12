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

        $paginator = Paginator::fromRequest($request, AuditLog::count(), 40);

        Response::html(View::render('admin/audit/index', [
            'title'     => Lang::get('admin.audit.title'),
            'entries'   => AuditLog::recent($paginator->perPage, $paginator->offset),
            'paginator' => $paginator,
        ], 'layout'));
    }
}
