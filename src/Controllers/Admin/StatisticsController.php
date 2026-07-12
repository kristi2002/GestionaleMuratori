<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Services\StatisticsService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/** Read-only statistics dashboard: KPI tiles + charts over the operational data. */
final class StatisticsController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/statistics/index', [
            'title' => Lang::get('admin.statistics.title'),
            'stats' => (new StatisticsService())->all(),
        ], 'layout'));
    }
}
