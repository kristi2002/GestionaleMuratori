<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\ProjectSubcontractorModel;
use App\Models\SubcontractorModel;
use App\Support\Lang;
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

        $search = trim((string) $request->input('q', ''));
        $model  = new SubcontractorModel();
        $links  = new ProjectSubcontractorModel();

        $subcontractors = $model->all($search);
        // Attach each subcontractor's assigned project ids for the assignment modal.
        foreach ($subcontractors as &$s) {
            $s['project_ids'] = $links->projectIdsFor((int) $s['id']);
        }
        unset($s);

        Response::html(View::render('admin/subcontractors/index', [
            'title'          => Lang::get('admin.subcontractors.title'),
            'subcontractors' => $subcontractors,
            'projects'       => (new ProjectModel())->all(),
            'search'         => $search,
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

        (new ProjectSubcontractorModel())->syncProjects((int) $id, $projectIds);
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

        return [
            'name'          => $name,
            'vat_or_tax_id' => $vat !== '' ? $vat : null,
            'email'         => $email !== '' ? $email : null,
            'phone'         => $phone !== '' ? $phone : null,
            'notes'         => $notes !== '' ? $notes : null,
        ];
    }
}
