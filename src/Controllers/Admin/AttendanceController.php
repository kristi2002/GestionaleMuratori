<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\SiteAttendanceModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Admin view of the Badge di Cantiere register: who entered/left a site and when,
 * filterable by project and day — the record an inspector or the firm's HSE office
 * consults. Read-only; entries are written from the field clock in/out screen.
 */
final class AttendanceController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projects  = (new ProjectModel())->all();
        $projectId = (int) $request->input('project_id', 0);
        // Default to the first project so the page always shows a concrete register.
        if ($projectId <= 0 && $projects !== []) {
            $projectId = (int) $projects[0]['id'];
        }
        $date = trim((string) $request->input('date', ''));

        $rows = $projectId > 0
            ? (new SiteAttendanceModel())->forProject($projectId, $date !== '' ? $date : null)
            : [];

        Response::html(View::render('admin/attendance/index', [
            'title'      => Lang::get('admin.attendance.title'),
            'projects'   => $projects,
            'projectId'  => $projectId,
            'date'       => $date,
            'attendance' => $rows,
        ], 'layout'));
    }
}
