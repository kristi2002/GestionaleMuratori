<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\ProjectSubcontractorModel;
use App\Models\SiteAttendanceModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validate;
use App\Support\View;

/**
 * Badge di Cantiere Digitale (Decreto 332/2026): the on-site clock in/out screen
 * for workers and subcontractor logins. Each timbratura records a timestamp and,
 * when the browser grants it, GPS coordinates — the register an inspector can pull
 * to see who was on a reconstruction site and when.
 */
final class AttendanceController
{
    private const ROLES = ['worker', 'subcontractor'];

    /** GET /attendance — current status (in/out) + clock buttons + recent history. */
    public function page(Request $request): void
    {
        AuthGuard::require($request, self::ROLES);

        $model = new SiteAttendanceModel();
        $monthStart = (new \DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd   = (new \DateTimeImmutable('last day of this month'))->format('Y-m-d');
        Response::html(View::render('attendance/index', [
            'title'    => Lang::get('attendance.title'),
            'open'     => $model->openForUser((int) Auth::id()),
            'projects' => $this->allowedProjects(),
            'recent'   => $model->recentForUser((int) Auth::id()),
            'stats'    => $model->monthlyStatsForUser((int) Auth::id(), $monthStart, $monthEnd),
        ], 'layout'));
    }

    /** POST /attendance/in — clock in at a project (single open attendance enforced). */
    public function clockIn(Request $request): void
    {
        $user  = AuthGuard::require($request, self::ROLES);
        $model = new SiteAttendanceModel();

        if ($model->openForUser((int) $user['id']) !== null) {
            Response::fail(Lang::get('attendance.already_in'), 422);
            return;
        }

        $projectId = (int) $request->input('project_id', 0);
        if (!$this->projectAllowed($projectId)) {
            Response::fail(Lang::get('attendance.project_invalid'), 422);
            return;
        }

        [$lat, $lng] = $this->coordinates($request);
        if ($lat === false) {
            Response::fail(Lang::get('attendance.coords_invalid'), 422);
            return;
        }

        $id = $model->clockIn([
            'project_id'       => $projectId,
            'user_id'          => (int) $user['id'],
            'subcontractor_id' => $user['role'] === 'subcontractor' ? Auth::subcontractorId() : null,
            'person_name'      => (string) $user['name'],
            'entry_at'         => date('Y-m-d H:i:s'),
            'entry_lat'        => $lat,
            'entry_lng'        => $lng,
        ]);

        Response::ok(['id' => $id]);
    }

    /** POST /attendance/out — close the caller's open attendance. */
    public function clockOut(Request $request): void
    {
        $user  = AuthGuard::require($request, self::ROLES);
        $model = new SiteAttendanceModel();

        $open = $model->openForUser((int) $user['id']);
        if ($open === null) {
            Response::fail(Lang::get('attendance.not_in'), 422);
            return;
        }

        [$lat, $lng] = $this->coordinates($request);
        if ($lat === false) {
            Response::fail(Lang::get('attendance.coords_invalid'), 422);
            return;
        }

        $model->clockOut((int) $open['id'], date('Y-m-d H:i:s'), $lat, $lng);
        Response::ok();
    }

    /** Projects the caller may clock into: workers → active projects; subs → assigned. */
    private function allowedProjects(): array
    {
        if (Auth::role() === 'subcontractor') {
            return (new ProjectSubcontractorModel())->projectsFor((int) Auth::subcontractorId());
        }
        return (new ProjectModel())->all(['status' => 'active']);
    }

    private function projectAllowed(int $projectId): bool
    {
        if ($projectId <= 0) {
            return false;
        }
        if (Auth::role() === 'subcontractor') {
            return (new ProjectSubcontractorModel())->isAssigned((int) Auth::subcontractorId(), $projectId);
        }
        $project = (new ProjectModel())->find($projectId);
        return $project !== null && $project['status'] === 'active';
    }

    /**
     * Parse optional GPS coordinates. Returns [null, null] when absent (GPS denied
     * is allowed) or [false, false] when present but out of range.
     *
     * @return array{0:string|null|false,1:string|null|false}
     */
    private function coordinates(Request $request): array
    {
        $lat = trim((string) $request->input('lat', ''));
        $lng = trim((string) $request->input('lng', ''));
        if ($lat === '' && $lng === '') {
            return [null, null];
        }
        if (!Validate::isLatitude($lat) || !Validate::isLongitude($lng)) {
            return [false, false];
        }
        return [$lat, $lng];
    }
}
