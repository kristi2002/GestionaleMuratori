<?php
declare(strict_types=1);

namespace App\Controllers\Sub;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\SubcontractorProjectGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Models\ProjectSubcontractorModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Subcontractor portal (subappalti). A subcontractor only ever sees the projects
 * it is assigned to (project_subcontractors) — never the company's inventory,
 * unit costs, or profit-relevant figures (§ roadmap Phase 3: "no inventory/cost exposure").
 */
final class ProjectController
{
    /** GET /sub — assigned projects for the logged-in subcontractor. */
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['subcontractor']);

        $projects = (new ProjectSubcontractorModel())->projectsFor((int) Auth::subcontractorId());

        Response::html(View::render('sub/index', [
            'title'    => Lang::get('sub.projects_title'),
            'projects' => $projects,
        ], 'layout'));
    }

    /** GET /sub/projects/{id} — read-only project detail with interventions and photos. */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['subcontractor']);
        $project = SubcontractorProjectGuard::require($request, $id, (int) Auth::subcontractorId());
        if ($project === null) {
            return;
        }

        $interventions = (new InterventionModel())->all(['project_id' => (int) $id]);
        $photoModel    = new PhotoModel();
        foreach ($interventions as &$intervention) {
            $intervention['gallery'] = $photoModel->forIntervention((int) $intervention['id']);
        }
        unset($intervention);

        Response::html(View::render('sub/show', [
            'title'         => $project['name'],
            'project'       => $project,
            'interventions' => $interventions,
        ], 'layout'));
    }
}
