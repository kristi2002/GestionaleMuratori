<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\ComplianceDocumentModel;
use App\Models\InterventionModel;
use App\Models\ProjectModel;
use App\Models\SiteAttendanceModel;
use App\Models\WarehouseItemModel;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

final class DashboardController
{
    /** GET / — send the user to their role landing page (or login). */
    public function home(Request $request): void
    {
        if (!Auth::check()) {
            Response::redirect(Url::to('/login'));
            return;
        }
        $home = Auth::homeFor(Auth::role());
        if (parse_url($home, PHP_URL_PATH) === '/login') {
            Auth::logout();
            Response::redirect(Url::to('/login'));
            return;
        }
        Response::redirect($home);
    }

    public function admin(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $todayDt       = new \DateTimeImmutable('today');
        $today         = $todayDt->format('Y-m-d');
        $interventions = new InterventionModel();

        // 14-day trend window, zero-filled so a quiet day reads as 0, not a gap.
        $days  = 14;
        $start = $todayDt->modify('-' . ($days - 1) . ' days');
        $from  = $start->format('Y-m-d');
        $dates = [];
        for ($i = 0; $i < $days; $i++) {
            $dates[] = $start->modify('+' . $i . ' days')->format('Y-m-d');
        }
        $fill = static fn (array $counts): array
            => array_map(static fn (string $d): int => $counts[$d] ?? 0, $dates);

        $trends = [
            'scheduled' => $fill($interventions->dailyCounts('scheduled_date', $from, $today)),
            'completed' => $fill($interventions->dailyCounts('completed_at', $from, $today)),
            'onsite'    => $fill((new SiteAttendanceModel())->dailyClockIns($from, $today)),
        ];

        Response::html(View::render('admin/dashboard', [
            'title'          => Lang::get('admin.dashboard.title'),
            'activeProjects' => (new ProjectModel())->countByStatus('active'),
            'openInterventions' => $interventions->countOpen(),
            'todayByStatus'  => $interventions->countsByStatusForDate($today),
            'lowStock'       => (new WarehouseItemModel())->lowStock(),
            'expiringDocs'   => (new ComplianceDocumentModel())->expiringSoon(30),
            'trends'         => $trends,
            'trendDays'      => $days,
            'today'          => $today,
        ], 'layout'));
    }

    /** GET /shortcuts — keyboard-shortcut reference, for any authenticated user. */
    public function shortcuts(Request $request): void
    {
        AuthGuard::require($request); // any authenticated user

        Response::html(View::render('shortcuts', [
            'title' => Lang::get('shortcuts.title'),
        ], 'layout'));
    }

    /** GET /health — public lightweight readiness probe. */
    public function health(Request $request): void
    {
        try {
            Database::pdo()->query('SELECT 1');
            Response::ok(['status' => 'ok']);
        } catch (\Throwable $e) {
            Response::fail('Database non raggiungibile.', 500);
        }
    }
}
