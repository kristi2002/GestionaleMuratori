<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ProjectModel;
use App\Models\ProjectSubcontractorModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * §6 RBAC — a subcontractor only ever sees projects it is assigned to
 * (project_subcontractors). Missing and not-mine are both reported as 404
 * (no existence leak, same pattern as ClientProjectGuard / InterventionOwnerGuard).
 */
final class SubcontractorProjectGuard
{
    public static function require(Request $request, string $projectId, int $subcontractorId): ?array
    {
        $project = (new ProjectModel())->find((int) $projectId);
        if ($project === null
            || !(new ProjectSubcontractorModel())->isAssigned($subcontractorId, (int) $projectId)) {
            if ($request->wantsJson()) {
                Response::fail(Lang::get('sub.not_found'), 404);
            } else {
                Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            }
            return null;
        }
        return $project;
    }
}
