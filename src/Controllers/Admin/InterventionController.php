<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\InterventionMaterialModel;
use App\Models\InterventionModel;
use App\Models\InterventionTaskModel;
use App\Models\InterventionTimeEntryModel;
use App\Models\PhotoModel;
use App\Models\ProjectModel;
use App\Models\UserModel;
use App\Models\WarehouseItemModel;
use App\Services\InterventionService;
use App\Services\NotificationService;
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

        $model  = new InterventionModel();
        $items  = $model->scheduledBetween($first->format('Y-m-d'), $first->format('Y-m-t'));
        $byDate = [];
        foreach ($items as $it) {
            $byDate[(string) $it['scheduled_date']][] = $it;
        }

        Response::html(View::render('admin/interventions/calendar', [
            'title'  => Lang::get('admin.interventions.calendar'),
            'month'  => $first,
            'byDate' => $byDate,
            'kpis'   => $this->kpiCounts($model),
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

        // Exact-day filter (e.g. the calendar "+N" overflow chip links here); it
        // pins date_from = date_to = that day and supersedes the range chips.
        $date = (string) $request->input('date', '');
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) {
            $filters['date_from'] = $date;
            $filters['date_to']   = $date;
            $range = '';
        } else {
            $date = '';
        }

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

        $statusCounts = $model->countsByStatus($filters);

        Response::html(View::render('admin/interventions/index', [
            'title'         => Lang::get('admin.interventions.title'),
            'interventions' => $interventions,
            'projects'      => (new ProjectModel())->all(),
            'workers'       => (new UserModel())->listByRole('worker'),
            'warehouseItems' => (new WarehouseItemModel())->all(),
            'filters'       => $filters,
            'statuses'      => self::STATUSES,
            'statusCounts'  => $statusCounts,
            'totalCount'    => array_sum($statusCounts),
            'kpis'          => $this->kpiCounts($model),
            'range'         => $range,
            'dateFilter'    => $date,
            'paginator'     => $paginator,
        ], 'layout'));
    }

    /**
     * GET /admin/interventions/dispatch — workload / dispatch board. Active
     * (non-completed) scheduled interventions for a 7-day window, grouped by worker
     * then day; flags any worker/day with 2+ jobs as a double-booking and offers a
     * quick worker reassignment per row. Unassigned work sits in its own bucket.
     */
    public function dispatch(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $fromRaw = (string) $request->input('from', '');
        $from    = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw)
            ? new \DateTimeImmutable($fromRaw)
            : new \DateTimeImmutable('today');
        $to = $from->modify('+6 days');

        $model = new InterventionModel();
        $rows  = $model->dispatchBetween($from->format('Y-m-d'), $to->format('Y-m-d'));

        // Bucket by [worker][date] for the drag-and-drop grid (0 = unassigned).
        $byCell = [];
        foreach ($rows as $r) {
            $wid = (int) ($r['assigned_worker_id'] ?? 0);
            $byCell[$wid][(string) $r['scheduled_date']][] = $r;
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $days  = [];
        for ($i = 0; $i < 7; $i++) {
            $d      = $from->modify("+{$i} days");
            $days[] = [
                'date'    => $d->format('Y-m-d'),
                'weekday' => Lang::label('weekdays_short', $d->format('N')),
                'day'     => $d->format('d/m'),
                'today'   => $d->format('Y-m-d') === $today,
            ];
        }

        Response::html(View::render('admin/interventions/dispatch', [
            'title'       => Lang::get('admin.interventions.dispatch'),
            'from'        => $from,
            'to'          => $to,
            'prev'        => $from->modify('-7 days')->format('Y-m-d'),
            'next'        => $from->modify('+7 days')->format('Y-m-d'),
            'days'        => $days,
            'byCell'      => $byCell,
            'unscheduled' => $model->unscheduledOpen(),
            'workers'     => (new UserModel())->listByRole('worker'),
            'kpis'        => $this->kpiCounts($model),
        ], 'layout'));
    }

    /**
     * POST /admin/interventions/{id}/reassign — set (worker_id) or clear (0/empty)
     * the assigned worker from the dispatch board.
     */
    public function reassign(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new InterventionModel();
        $iv    = $model->find((int) $id);
        if ($iv === null) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $workerId = (int) $request->input('worker_id', 0);
        if ($workerId > 0) {
            $worker = (new UserModel())->findById($workerId);
            if ($worker === null || $worker['role'] !== 'worker') {
                Response::fail(Lang::get('admin.interventions.worker_invalid'), 422);
                return;
            }
        }

        $model->reassign((int) $id, $workerId > 0 ? $workerId : null);

        // Alert the newly-assigned worker on their own feed (and push their devices).
        // Only on an actual (re)assignment to a different worker — not on unassign.
        if ($workerId > 0 && (int) ($iv['assigned_worker_id'] ?? 0) !== $workerId) {
            NotificationService::notifyUser($workerId, [
                'type'      => 'intervention_assigned',
                'severity'  => 'info',
                'title'     => sprintf(Lang::get('notifications.intervention_assigned'), (string) $iv['title']),
                'body'      => sprintf(
                    Lang::get('notifications.intervention_assigned_body'),
                    (string) $iv['project_name'],
                    $iv['scheduled_date'] !== null ? (string) $iv['scheduled_date'] : '—'
                ),
                'link'      => '/worker/interventions/' . $id,
                'dedup_key' => 'intervention_assigned:' . $id . ':' . $workerId,
            ]);
        }

        Response::ok();
    }

    /**
     * POST /admin/interventions/{id}/schedule — set worker AND scheduled date in one
     * write (dispatch board drag-and-drop). worker_id 0 = unassign; empty date = unschedule.
     */
    public function schedule(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new InterventionModel();
        $iv    = $model->find((int) $id);
        if ($iv === null) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $workerId = (int) $request->input('worker_id', 0);
        if ($workerId > 0) {
            $worker = (new UserModel())->findById($workerId);
            if ($worker === null || $worker['role'] !== 'worker') {
                Response::fail(Lang::get('admin.interventions.worker_invalid'), 422);
                return;
            }
        }

        $dateRaw = trim((string) $request->input('scheduled_date', ''));
        $date    = null;
        if ($dateRaw !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
                Response::fail(Lang::get('admin.interventions.date_invalid'), 422);
                return;
            }
            $date = $dateRaw;
        }

        $model->schedule((int) $id, $workerId > 0 ? $workerId : null, $date);

        // Alert the newly-assigned worker (same rule/dedup as reassign()).
        if ($workerId > 0 && (int) ($iv['assigned_worker_id'] ?? 0) !== $workerId) {
            NotificationService::notifyUser($workerId, [
                'type'      => 'intervention_assigned',
                'severity'  => 'info',
                'title'     => sprintf(Lang::get('notifications.intervention_assigned'), (string) $iv['title']),
                'body'      => sprintf(
                    Lang::get('notifications.intervention_assigned_body'),
                    (string) $iv['project_name'],
                    $date !== null ? $date : '—'
                ),
                'link'      => '/worker/interventions/' . $id,
                'dedup_key' => 'intervention_assigned:' . $id . ':' . $workerId,
            ]);
        }

        Response::ok();
    }

    /** Header KPI counts shared by the list + calendar views (single query). */
    private function kpiCounts(InterventionModel $model): array
    {
        return $model->summaryCounts(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            (new \DateTimeImmutable('monday this week'))->format('Y-m-d'),
            (new \DateTimeImmutable('sunday this week'))->format('Y-m-d'),
            (new \DateTimeImmutable('first day of this month'))->format('Y-m-d'),
            (new \DateTimeImmutable('last day of this month'))->format('Y-m-d'),
        );
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
            'tasks'        => (new InterventionTaskModel())->forIntervention((int) $id),
            'timeByWorker' => (new InterventionTimeEntryModel())->perWorker((int) $id),
        ], 'layout'));
    }

    /** POST /admin/interventions/{id}/tasks — add a checklist item. */
    public function addTask(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);
        if ((new InterventionModel())->find((int) $id) === null) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '' || mb_strlen($label) > 255) {
            Response::fail(Lang::get('admin.interventions.task_label_required'), 422);
            return;
        }

        $taskId = (new InterventionTaskModel())->create([
            'intervention_id' => (int) $id,
            'label'           => $label,
            'created_by'      => (int) Auth::id(),
        ]);
        Response::ok(['id' => $taskId]);
    }

    /** POST /admin/interventions/{id}/tasks/{taskId}/toggle — tick/untick a checklist item. */
    public function toggleTask(Request $request, string $id, string $taskId): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new InterventionTaskModel();
        $task  = $model->find((int) $taskId);
        if ($task === null || (int) $task['intervention_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $model->setDone((int) $taskId, (int) $request->input('done', 0) === 1, (int) Auth::id());
        Response::ok();
    }

    /** POST /admin/interventions/{id}/tasks/{taskId}/delete — remove a checklist item. */
    public function deleteTask(Request $request, string $id, string $taskId): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new InterventionTaskModel();
        $task  = $model->find((int) $taskId);
        if ($task === null || (int) $task['intervention_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.interventions.not_found'), 404);
            return;
        }

        $model->delete((int) $taskId);
        Response::ok();
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
