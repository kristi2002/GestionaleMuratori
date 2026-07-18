<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\ComplianceDocumentModel;
use App\Models\InterventionModel;
use App\Models\ProjectModel;
use App\Models\SiteAttendanceModel;
use App\Models\UserModel;
use App\Models\WarehouseItemModel;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Shortcuts;
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

        // Fresh overrides from the DB so the editor reflects the saved state even
        // if the session snapshot predates the last save.
        $stored = Auth::role() === 'admin'
            ? (new UserModel())->findById((int) Auth::id())['shortcuts'] ?? null
            : null;

        Response::html(View::render('shortcuts', [
            'title'      => Lang::get('shortcuts.title'),
            'shortcuts'  => Shortcuts::effective($stored),
        ], 'layout'));
    }

    /** POST /shortcuts — save the admin's navigation-shortcut overrides (JSON). */
    public function saveShortcuts(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $raw = $request->input('shortcuts', []);
        if (!is_array($raw)) {
            $raw = [];
        }

        [$overrides, $error] = Shortcuts::validate($raw);
        if ($error !== null) {
            Response::fail(Lang::get($error), 422);
            return;
        }

        $json = $overrides === [] ? null : json_encode($overrides, JSON_UNESCAPED_SLASHES);
        (new UserModel())->saveShortcuts((int) Auth::id(), $json);

        // Reflect the change in the session so the global handler updates without
        // a re-login (Auth::user() is a session snapshot).
        $user = Auth::user();
        $user['shortcuts'] = $json;
        Session::set('user', $user);

        Response::json(['ok' => true, 'data' => ['shortcuts' => Shortcuts::effective($json)]]);
    }

    /** GET /health — public lightweight readiness probe. */
    public function health(Request $request): void
    {
        try {
            Database::pdo()->query('SELECT 1');
            Response::ok(['status' => 'ok']);
        } catch (\Throwable $e) {
            Response::fail(Lang::get('errors.db_unreachable'), 500);
        }
    }
}
