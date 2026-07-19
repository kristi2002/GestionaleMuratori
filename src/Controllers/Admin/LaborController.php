<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Services\LaborCostService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Labor-hours costing report (/admin/financials/labor): hours and euro cost per
 * cantiere and per person, computed from the Badge di Cantiere register and the
 * per-worker / per-subcontractor hourly rates. Complements the financials P&L,
 * which now folds this labor cost into each project's margin.
 */
final class LaborController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/labor/index', [
            'title' => Lang::get('admin.labor.title'),
            'labor' => (new LaborCostService())->summary(),
        ], 'layout'));
    }
}
