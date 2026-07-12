<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Services\FinancialsService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/** Per-cantiere cash-in / cash-out / margin dashboard (read-only, admin). */
final class FinancialsController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/financials/index', [
            'title'   => Lang::get('admin.financials.title'),
            'finance' => (new FinancialsService())->all(),
        ], 'layout'));
    }
}
