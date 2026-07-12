<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\InterventionMaterialModel;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use App\Models\WarehouseItemModel;
use App\Services\InterventionService;
use App\Services\PhotoStreamService;
use App\Support\Auth;
use App\Support\Csv;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validate;
use App\Support\View;
use RuntimeException;

final class InterventionController
{
    private const STATUSES = ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    /** §8 "cheap win": quick dispatch filters reusing scheduled_date, no new table. */
    private const DATE_RANGES = ['today', 'week'];

    /** GET /admin/interventions/calendar — month grid of scheduled interventions. */
    public function calendar(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $month = (string) $request->input('month', '');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $first = new \DateTimeImmutable($month . '-01');

        $items  = (new InterventionModel())->scheduledBetween($first->format('Y-m-d'), $first->format('Y-m-t'));
        $byDate = [];
        foreach ($items as $it) {
            $byDate[(string) $it['scheduled_date']][] = $it;
        }

        Response::html(View::render('admin/interventions/calendar', [
            'title'  => Lang::get('admin.interventions.calendar'),
            'month'  => $first,
            'byDate' => $byDate,
            'prev'   => $first->modify('-1 month')->format('Y-m'),
            'next'   => $first->modify('+1 month')->format('Y-m'),
        ], 'layout'));
    }

    /** GET /admin/interventions/export — CSV of the currently-filtered interventions. */
    public function exportCsv(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $range = (string) $request->input('range', '');
        if (!in_array($range, self::DATE_RANGES, true)) {
            $range = '';
        }
        $filters = [
            'project_id' => (int) $request->input('project_id', 0),
            'worker_id'  => (int) $request->input('worker_id', 0),
            'status'     => (string) $request->input('status', ''),
        ];
        [$filters['date_from'], $filters['date_to']] = $this->dateRangeBounds($range);

        $rows = (new InterventionModel())->all($filters);
        $data = array_map(static fn (array $i): array => [
            $i['title'],
            $i['project_name'],
            $i['worker_name'] ?? '',
            $i['scheduled_date'],
            Lang::label('intervention_status', (string) $i['status']),
        ], $rows);

        Csv::send('interventi.csv', [
            Lang::get('admin.interventions.field_title'),
            Lang::get('admin.interventions.project'),
            Lang::get('admin.interventions.worker'),
            Lang::get('admin.interventions.scheduled_date'),
            Lang::get('admin.interventions.status'),
        ], $data);
    }

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $range = (string) $request->input('range', '');
        if (!in_array($range, self::DATE_RANGES, true)) {
            $range = '';
        }

        $filters = [
            'project_id' => (int) $request->input('project_id', 0),
            'worker_id'  => (int) $request->input('worker_id', 0),
            'status'     => (string) $request->input('status', ''),
        ];
        [$filters['date_from'], $filters['date_to']] = $this->dateRangeBounds($range);

        $model         = new InterventionModel();
        $paginator     = Paginator::fromRequest($request, $model->count($filters), 25);
        $interventions = $model->all($filters, $paginator->perPage, $paginator->offset);
        // One query for all materials instead of one per intervention (no N+1).
        $materialsById = (new InterventionMaterialModel())->forInterventions(
            array_map(static fn (array $i): int => (int) $i['id'], $interventions)
        );
        foreach ($interventions as &$intervention) {
            $intervention['materials'] = $materialsById[(int) $intervention['id']] ?? [];
        }
        unset($intervention);

        Response::html(View::render('admin/interventions/index', [
            'title'         => Lang::get('admin.interventions.title'),
            'interventions' => $interventions,
            'projects'      => (new ProjectModel())->all(),
            'workers'       => (new UserModel())->listByRole('worker'),
            'warehouseItems' => (new WarehouseItemModel())->all(),
            'filters'       => $filters,
            'statuses'      => self::STATUSES,
            'range'         => $range,
            'paginator'     => $paginator,
        ], 'layout'));
    }

    /** GET /admin/interventions/create — blank intervention form (with material editor). */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/interventions/form', [
            'title'          => Lang::get('admin.interventions.new'),
            'intervention'   => null,
            'materials'      => [],
            'projects'       => (new ProjectModel())->all(),
            'workers'        => (new UserModel())->listByRole('worker'),
            'warehouseItems' => (new WarehouseItemModel())->all(),
            'statuses'       => self::STATUSES,
        ], 'layout'));
    }

    /** GET /admin/interventions/{id}/edit — basic fields (materials are set at creation only). */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $intervention = (new InterventionModel())->find((int) $id);
        if ($intervention === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/interventions/form', [
            'title'          => Lang::get('admin.interventions.edit'),
            'intervention'   => $intervention,
            'materials'      => (new InterventionMaterialModel())->forIntervention((int) $id),
            'projects'       => (new ProjectModel())->all(),
            'workers'        => (new UserModel())->listByRole('worker'),
            'warehouseItems' => (new WarehouseItemModel())->all(),
            'statuses'       => self::STATUSES,
        ], 'layout'));
    }

    /** GET /admin/interventions/{id} — full detail: materials, history, photos, signature (gap F2). */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model        = new InterventionModel();
        $intervention = $model->find((int) $id);
        if ($intervention === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $photos       = (new PhotoModel())->forIntervention((int) $id);
        $photosByType = ['before' => [], 'during' => [], 'after' => []];
        foreach ($photos as $photo) {
            $photosByType[$photo['type']][] = $photo;
        }

        Response::html(View::render('admin/interventions/show', [
            'title'        => $intervention['title'],
            'intervention' => $intervention,
            'materials'    => (new InterventionMaterialModel())->forIntervention((int) $id),
            'history'      => $model->statusHistory((int) $id),
            'photosByType' => $photosByType,
        ], 'layout'));
    }

    /** GET /admin/interventions/{id}/signature — streams the client signature PNG. */
    public function signature(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $intervention = (new InterventionModel())->find((int) $id);
        if ($intervention === null
            || !(new PhotoStreamService())->streamFile($intervention['client_signature_path'])) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
        }
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validatedFields($request);
        if ($data === null) {
            return;
        }

        $materials = $this->validatedMaterials($request);
        if ($materials === null) {
            return;
        }

        try {
            $id = (new InterventionService())->create($data, $materials, (int) Auth::id());
            Response::ok(['id' => $id]);
        } catch (RuntimeException $e) {
            Response::fail($e->getMessage(), 422);
        }
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model        = new InterventionModel();
        $intervention = $model->find((int) $id);
        if ($intervention === null) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $data = $this->validatedFields($request, updating: true);
        if ($data === null) {
            return;
        }

        $model->updateBasic((int) $id, $data);
        Response::ok();
    }

    public function status(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $toStatus = (string) $request->input('to_status', '');
        if (!in_array($toStatus, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.interventions.transition_invalid'), 422);
            return;
        }

        try {
            (new InterventionService())->transition((int) $id, $toStatus, (int) Auth::id());
            Response::ok();
        } catch (RuntimeException $e) {
            Response::fail($e->getMessage(), 422);
        }
    }

    /** @return array{0:?string,1:?string} [date_from, date_to], both null when no range is selected. */
    private function dateRangeBounds(string $range): array
    {
        if ($range === 'today') {
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            return [$today, $today];
        }
        if ($range === 'week') {
            $monday = (new \DateTimeImmutable('monday this week'))->format('Y-m-d');
            $sunday = (new \DateTimeImmutable('sunday this week'))->format('Y-m-d');
            return [$monday, $sunday];
        }
        return [null, null];
    }

    /** @return array<string,mixed>|null Validated top-level fields, or null if a fail response was already sent. */
    private function validatedFields(Request $request, bool $updating = false): ?array
    {
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Response::fail(Lang::get('admin.interventions.title_required'), 422);
            return null;
        }

        $workerId = (int) $request->input('assigned_worker_id', 0);
        if ($workerId > 0) {
            $worker = (new UserModel())->findById($workerId);
            if ($worker === null || $worker['role'] !== 'worker') {
                Response::fail(Lang::get('admin.interventions.worker_invalid'), 422);
                return null;
            }
        }

        $description     = trim((string) $request->input('description', ''));
        $scheduledDate    = trim((string) $request->input('scheduled_date', ''));
        $scheduledTime    = trim((string) $request->input('scheduled_start_time', ''));

        $data = [
            'assigned_worker_id'   => $workerId > 0 ? $workerId : null,
            'title'                => $title,
            'description'          => $description !== '' ? $description : null,
            'scheduled_date'       => $scheduledDate !== '' ? $scheduledDate : null,
            'scheduled_start_time' => $scheduledTime !== '' ? $scheduledTime : null,
        ];

        if ($updating) {
            return $data;
        }

        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0) {
            Response::fail(Lang::get('admin.interventions.project_required'), 422);
            return null;
        }
        if ((new ProjectModel())->find($projectId) === null) {
            Response::fail(Lang::get('admin.interventions.project_invalid'), 422);
            return null;
        }
        $data['project_id'] = $projectId;

        return $data;
    }

    /** @return array<int,array{item_id:int,qty_planned:string}>|null */
    private function validatedMaterials(Request $request): ?array
    {
        $itemIds = (array) $request->input('item_id', []);
        $qtys    = (array) $request->input('qty_planned', []);

        $materials = [];
        $seen      = [];
        foreach ($itemIds as $index => $rawItemId) {
            $itemId = (int) $rawItemId;
            $rawQty = trim((string) ($qtys[$index] ?? ''));
            if ($itemId <= 0 && $rawQty === '') {
                continue; // ignore a fully empty trailing row from the repeater
            }
            if ($itemId <= 0) {
                Response::fail(Lang::get('admin.interventions.material_item_required'), 422);
                return null;
            }
            if (!Validate::isQty($rawQty) || (float) $rawQty <= 0) {
                Response::fail(Lang::get('admin.interventions.material_qty_invalid'), 422);
                return null;
            }
            if (isset($seen[$itemId])) {
                Response::fail(Lang::get('admin.interventions.material_duplicate'), 422);
                return null;
            }
            $seen[$itemId] = true;
            $materials[]   = ['item_id' => $itemId, 'qty_planned' => $rawQty];
        }

        return $materials;
    }
}
