<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\ProjectModel;
use App\Models\StockLocationModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class ProjectController
{
    private const STATUSES = ['active', 'on_hold', 'closed'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'search'    => trim((string) $request->input('q', '')),
            'client_id' => (int) $request->input('client_id', 0),
            'status'    => (string) $request->input('status', ''),
        ];

        Response::html(View::render('admin/projects/index', [
            'title'    => Lang::get('admin.projects.title'),
            'projects' => (new ProjectModel())->all($filters),
            'clients'  => (new ClientModel())->all(),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $id = (new ProjectModel())->create($data);
        // Every project gets its own site location so material can be transferred
        // warehouse -> cantiere and tracked with a per-site balance.
        (new StockLocationModel())->ensureForProject($id, (string) $data['name']);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectModel();
        $project = $model->find((int) $id);
        if ($project === null) {
            Response::fail(Lang::get('admin.projects.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.projects.name_required'), 422);
            return null;
        }

        $clientId = (int) $request->input('client_id', 0);
        if ($clientId <= 0) {
            Response::fail(Lang::get('admin.projects.client_required'), 422);
            return null;
        }
        if ((new ClientModel())->find($clientId) === null) {
            Response::fail(Lang::get('admin.projects.client_invalid'), 422);
            return null;
        }

        $startDate = trim((string) $request->input('start_date', ''));
        if (!$this->isValidDate($startDate)) {
            Response::fail(Lang::get('admin.projects.start_date_required'), 422);
            return null;
        }

        $endDate = trim((string) $request->input('end_date', ''));
        $endDate = $endDate !== '' ? $endDate : null;
        if ($endDate !== null && (!$this->isValidDate($endDate) || $endDate < $startDate)) {
            Response::fail(Lang::get('admin.projects.end_date_invalid'), 422);
            return null;
        }

        $status = (string) $request->input('status', 'active');
        if (!in_array($status, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.projects.status_invalid'), 422);
            return null;
        }

        $invoiceReference = trim((string) $request->input('invoice_reference', ''));
        $location          = trim((string) $request->input('location', ''));

        return [
            'client_id'         => $clientId,
            'name'              => $name,
            'location'          => $location !== '' ? $location : null,
            'start_date'        => $startDate,
            'end_date'          => $endDate,
            'invoice_reference' => $invoiceReference !== '' ? $invoiceReference : null,
            'status'            => $status,
        ];
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
