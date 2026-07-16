<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\ClientProjectGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class ProjectController
{
    /** Photo types shown to the client (§1 — "before/after"; 'during' is worker-only progress detail). */
    private const GALLERY_TYPES = ['before', 'after'];

    /** GET /client — read-only list of the logged-in client's own projects (§6). */
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['client']);

        $projects = (new ProjectModel())->all(['client_id' => (int) Auth::clientId()]);

        Response::html(View::render('client/index', [
            'title'    => Lang::get('client.projects_title'),
            'projects' => $projects,
        ], 'layout'));
    }

    /** GET /client/projects/{id} — project summary + read-only interventions with before/after photos. */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['client']);
        $project = ClientProjectGuard::require($request, $id, (int) Auth::clientId());
        if ($project === null) {
            return;
        }

        $interventions = (new InterventionModel())->all(['project_id' => (int) $id]);
        $photoModel    = new PhotoModel();
        foreach ($interventions as &$intervention) {
            $photos = $photoModel->forIntervention((int) $intervention['id']);
            $intervention['gallery'] = array_values(array_filter(
                $photos,
                static fn (array $p): bool => in_array($p['type'], self::GALLERY_TYPES, true)
            ));
        }
        unset($intervention);

        // Read-only billing view: the client sees issued/paid invoices for this
        // project (never drafts, which are still being prepared by the office).
        $invoices = array_values(array_filter(
            (new ProjectInvoiceModel())->forProject((int) $id),
            static fn (array $inv): bool => $inv['status'] !== 'draft'
        ));

        Response::html(View::render('client/show', [
            'title'         => $project['name'],
            'project'       => $project,
            'interventions' => $interventions,
            'invoices'      => $invoices,
        ], 'layout'));
    }
}
