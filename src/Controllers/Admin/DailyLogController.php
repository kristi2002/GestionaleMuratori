<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\DailyLogModel;
use App\Models\EquipmentModel;
use App\Models\ProjectModel;
use App\Services\WeatherService;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Giornale dei Lavori (DPR 380/2001). One log per project per day; weather is
 * auto-filled from the project coordinates (Open-Meteo) on creation, and a log
 * becomes read-only once closed to preserve legal integrity.
 */
final class DailyLogController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projects  = (new ProjectModel())->all();
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0 && $projects !== []) {
            $projectId = (int) $projects[0]['id'];
        }

        $logs = $projectId > 0 ? (new DailyLogModel())->forProject($projectId) : [];

        Response::html(View::render('admin/daily_logs/index', [
            'title'     => Lang::get('admin.daily_logs.title'),
            'projects'  => $projects,
            'projectId' => $projectId,
            'logs'      => $logs,
            'today'     => (new \DateTimeImmutable('today'))->format('Y-m-d'),
        ], 'layout'));
    }

    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $log = (new DailyLogModel())->find((int) $id);
        if ($log === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $dlModel = new DailyLogModel();
        Response::html(View::render('admin/daily_logs/show', [
            'title'         => Lang::get('admin.daily_logs.title'),
            'log'           => $log,
            'equipment'     => (new EquipmentModel())->listActive(),
            'equipmentIds'  => $dlModel->equipmentIds((int) $id),
            'weatherCodes'  => WeatherService::WMO,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projectModel = new ProjectModel();
        $projectId    = (int) $request->input('project_id', 0);
        $project      = $projectModel->find($projectId);
        if ($project === null) {
            Response::fail(Lang::get('admin.daily_logs.project_invalid'), 422);
            return;
        }

        $date = trim((string) $request->input('log_date', ''));
        if (!$this->isValidPastOrToday($date)) {
            Response::fail(Lang::get('admin.daily_logs.date_invalid'), 422);
            return;
        }

        $model = new DailyLogModel();
        if ($model->findForProjectDate($projectId, $date) !== null) {
            Response::fail(Lang::get('admin.daily_logs.duplicate'), 422);
            return;
        }

        $workers = $this->parseWorkers($request);
        if ($workers === false) {
            Response::fail(Lang::get('admin.daily_logs.workers_invalid'), 422);
            return;
        }

        $data = [
            'project_id'      => $projectId,
            'log_date'        => $date,
            'workers_present' => $workers,
            'work_done'       => $this->nullable($request->input('work_done', '')),
            'notes'           => $this->nullable($request->input('notes', '')),
            'created_by'      => Auth::id(),
        ];

        // Auto-fill weather from the project's coordinates unless typed manually.
        $manualWeather = trim((string) $request->input('weather_text', ''));
        if ($manualWeather !== '') {
            $data['weather_text'] = $manualWeather;
        } else {
            $weather = (new WeatherService())->forDate(
                $project['lat'] !== null ? (string) $project['lat'] : null,
                $project['lng'] !== null ? (string) $project['lng'] : null,
                $date
            );
            if ($weather !== null) {
                $data['weather_text'] = $weather['weather_text'];
                $data['weather_code'] = $weather['weather_code'];
                $data['temp_min']     = $weather['temp_min'];
                $data['temp_max']     = $weather['temp_max'];
            }
        }

        $newId = $model->create($data);
        Response::ok(['id' => $newId]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new DailyLogModel();
        $log   = $model->find((int) $id);
        if ($log === null) {
            Response::fail(Lang::get('admin.daily_logs.not_found'), 404);
            return;
        }
        if ((int) $log['is_closed'] === 1) {
            Response::fail(Lang::get('admin.daily_logs.closed'), 422);
            return;
        }

        $workers = $this->parseWorkers($request);
        if ($workers === false) {
            Response::fail(Lang::get('admin.daily_logs.workers_invalid'), 422);
            return;
        }

        $model->update((int) $id, [
            'weather_text'    => $this->nullable($request->input('weather_text', '')),
            'weather_code'    => $log['weather_code'],
            'temp_min'        => $this->nullableNumber($request->input('temp_min', '')),
            'temp_max'        => $this->nullableNumber($request->input('temp_max', '')),
            'workers_present' => $workers,
            'work_done'       => $this->nullable($request->input('work_done', '')),
            'notes'           => $this->nullable($request->input('notes', '')),
        ]);
        Response::ok();
    }

    public function close(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new DailyLogModel();
        $log   = $model->find((int) $id);
        if ($log === null) {
            Response::fail(Lang::get('admin.daily_logs.not_found'), 404);
            return;
        }
        if ((int) $log['is_closed'] === 1) {
            Response::fail(Lang::get('admin.daily_logs.already_closed'), 422);
            return;
        }

        $model->close((int) $id, (int) Auth::id());
        Response::ok();
    }

    /** POST /admin/daily-logs/{id}/equipment — replace the equipment set (open logs only). */
    public function equipment(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new DailyLogModel();
        $log   = $model->find((int) $id);
        if ($log === null) {
            Response::fail(Lang::get('admin.daily_logs.not_found'), 404);
            return;
        }
        if ((int) $log['is_closed'] === 1) {
            Response::fail(Lang::get('admin.daily_logs.closed'), 422);
            return;
        }

        $ids = $request->input('equipment_ids', []);
        $model->syncEquipment((int) $id, is_array($ids) ? $ids : []);
        Response::ok();
    }

    /** POST /admin/equipment — add an equipment catalog item. */
    public function storeEquipment(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.daily_logs.equipment_name_required'), 422);
            return;
        }
        $eid = (new EquipmentModel())->create($name);
        Response::ok(['id' => $eid]);
    }

    private function isValidPastOrToday(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return false;
        }
        return $value <= (new \DateTimeImmutable('today'))->format('Y-m-d');
    }

    /** @return int|null|false null when blank, false when invalid, else the count. */
    private function parseWorkers(Request $request): int|null|false
    {
        $raw = trim((string) $request->input('workers_present', ''));
        if ($raw === '') {
            return null;
        }
        if (!ctype_digit($raw)) {
            return false;
        }
        return (int) $raw;
    }

    private function nullable(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }

    private function nullableNumber(mixed $value): ?string
    {
        $v = trim((string) $value);
        return ($v !== '' && is_numeric($v)) ? $v : null;
    }
}
