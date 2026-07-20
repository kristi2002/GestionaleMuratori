<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ComplianceDocumentModel;
use App\Models\ProjectModel;
use App\Models\ProjectSubcontractorModel;
use App\Models\SubcontractorModel;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Admin management of subcontractors (subappaltatori): CRUD plus assignment to the
 * projects each may access from its portal. A subcontractor login (role=subcontractor)
 * is created separately through the users panel and linked via subcontractor_id.
 */
final class SubcontractorController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search    = trim((string) $request->input('q', ''));
        $model     = new SubcontractorModel();
        $links     = new ProjectSubcontractorModel();
        $paginator = Paginator::fromRequest($request, $model->count($search), 24);

        $subcontractors = $model->all($search, $paginator->perPage, $paginator->offset);
        // Batch-load assigned project ids for the whole page in one query (no N+1).
        $projectIds = $links->projectIdsForMany(
            array_map(static fn (array $s): int => (int) $s['id'], $subcontractors)
        );
        foreach ($subcontractors as &$s) {
            $s['project_ids'] = $projectIds[(int) $s['id']] ?? [];
        }
        unset($s);

        Response::html(View::render('admin/subcontractors/index', [
            'title'          => Lang::get('admin.subcontractors.title'),
            'subcontractors' => $subcontractors,
            'projects'       => (new ProjectModel())->all(),
            'compliance'     => (new ComplianceDocumentModel())->statusForSubjects('subcontractor'),
            'stats'          => $model->stats(),
            'search'         => $search,
            'paginator'      => $paginator,
        ], 'layout'));
    }

    /** GET /admin/subcontractors/create — blank subcontractor form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/subcontractors/form', [
            'title'         => Lang::get('admin.subcontractors.new'),
            'subcontractor' => null,
        ], 'layout'));
    }

    /** GET /admin/subcontractors/{id}/edit — populated subcontractor form page. */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $subcontractor = (new SubcontractorModel())->find((int) $id);
        if ($subcontractor === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/subcontractors/form', [
            'title'         => Lang::get('admin.subcontractors.edit'),
            'subcontractor' => $subcontractor,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $id = (new SubcontractorModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new SubcontractorModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.subcontractors.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function toggleActive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new SubcontractorModel();
        $sub   = $model->find((int) $id);
        if ($sub === null) {
            Response::fail(Lang::get('admin.subcontractors.not_found'), 404);
            return;
        }

        $model->setActive((int) $id, ((int) $sub['is_active']) !== 1);
        Response::ok();
    }

    /** POST /admin/subcontractors/{id}/projects — replace the assigned-project set. */
    public function assignProjects(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        if ((new SubcontractorModel())->find((int) $id) === null) {
            Response::fail(Lang::get('admin.subcontractors.not_found'), 404);
            return;
        }

        $projectIds = $request->input('project_ids', []);
        if (!is_array($projectIds)) {
            $projectIds = [];
        }

        // Compliance gate: refuse to assign a subcontractor to a site when its DURC
        // is expired or its patente a crediti is below 15 — unless the admin
        // explicitly overrides (force=1), which is recorded in the audit trail.
        $force = (string) $request->input('force', '') === '1';
        if ($projectIds !== [] && !$force) {
            $gate = (new ComplianceDocumentModel())->subcontractorGate((int) $id);
            if ($gate['blocked']) {
                $labels = array_map(
                    static fn (string $code): string => Lang::get('admin.subcontractors.gate_' . $code),
                    $gate['issues']
                );
                Response::fail(
                    sprintf(Lang::get('admin.subcontractors.gate_blocked'), implode(', ', $labels)),
                    422
                );
                return;
            }
        }

        (new ProjectSubcontractorModel())->syncProjects((int) $id, $projectIds);
        if ($force) {
            \App\Support\AuditLog::record('compliance_override', 'subcontractor', (int) $id, '');
        }
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.subcontractors.name_required'), 422);
            return null;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::fail(Lang::get('admin.subcontractors.email_invalid'), 422);
            return null;
        }

        $vat   = trim((string) $request->input('vat_or_tax_id', ''));
        $phone = trim((string) $request->input('phone', ''));
        $notes = trim((string) $request->input('notes', ''));

        // Labor charge rate (euros/hour). Accepts an Italian comma or a dot; blank = none.
        $hourlyRate = null;
        $rateRaw    = trim((string) $request->input('hourly_rate', ''));
        if ($rateRaw !== '') {
            $normalized = str_replace(',', '.', $rateRaw);
            if (!is_numeric($normalized) || (float) $normalized < 0 || (float) $normalized > 99999999.99) {
                Response::fail(Lang::get('admin.subcontractors.rate_invalid'), 422);
                return null;
            }
            $hourlyRate = round((float) $normalized, 2);
        }

        return [
            'name'          => $name,
            'vat_or_tax_id' => $vat !== '' ? $vat : null,
            'email'         => $email !== '' ? $email : null,
            'phone'         => $phone !== '' ? $phone : null,
            'notes'         => $notes !== '' ? $notes : null,
            'hourly_rate'   => $hourlyRate,
        ];
    }
}
