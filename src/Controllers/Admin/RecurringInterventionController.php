<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\RecurringInterventionModel;
use App\Models\UserModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Recurring interventions (maintenance plans). Admins define a template that the
 * daily scheduler (App\Services\SchedulerService) materialises into real
 * interventions on each due date. Templates carry no materials (no stock coupling);
 * materials, if any, are added per generated occurrence.
 */
final class RecurringInterventionController
{
    private const FREQUENCIES = ['weekly', 'monthly'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/recurring/index', [
            'title' => Lang::get('admin.recurring.title'),
            'plans' => (new RecurringInterventionModel())->all(),
        ], 'layout'));
    }

    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);
        $this->renderForm(null);
    }

    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);
        $plan = (new RecurringInterventionModel())->find((int) $id);
        if ($plan === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }
        $this->renderForm($plan);
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $data['created_by']    = (int) Auth::id();
        $data['next_run_date'] = $data['start_date'];

        $id = (new RecurringInterventionModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new RecurringInterventionModel();
        $plan  = $model->find((int) $id);
        if ($plan === null) {
            Response::fail(Lang::get('admin.recurring.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        // Keep the running schedule, but never earlier than the (possibly new) start date.
        $data['next_run_date'] = max((string) $plan['next_run_date'], $data['start_date']);

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function toggleActive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new RecurringInterventionModel();
        $plan  = $model->find((int) $id);
        if ($plan === null) {
            Response::fail(Lang::get('admin.recurring.not_found'), 404);
            return;
        }
        $model->setActive((int) $id, ((int) $plan['is_active']) !== 1);
        Response::ok();
    }

    public function delete(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new RecurringInterventionModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.recurring.not_found'), 404);
            return;
        }
        $model->delete((int) $id);
        Response::ok();
    }

    private function renderForm(?array $plan): void
    {
        Response::html(View::render('admin/recurring/form', [
            'title'       => Lang::get($plan === null ? 'admin.recurring.new' : 'admin.recurring.edit'),
            'plan'        => $plan,
            'projects'    => (new ProjectModel())->all(),
            'workers'     => (new UserModel())->listByRole('worker'),
            'frequencies' => self::FREQUENCIES,
        ], 'layout'));
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was sent. */
    private function validated(Request $request): ?array
    {
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0 || (new ProjectModel())->find($projectId) === null) {
            Response::fail(Lang::get('admin.recurring.project_required'), 422);
            return null;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '' || mb_strlen($title) > 190) {
            Response::fail(Lang::get('admin.recurring.title_required'), 422);
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

        $frequency = (string) $request->input('frequency', '');
        if (!in_array($frequency, self::FREQUENCIES, true)) {
            Response::fail(Lang::get('admin.recurring.frequency_invalid'), 422);
            return null;
        }

        $interval = (int) $request->input('interval_count', 1);
        if ($interval < 1 || $interval > 52) {
            Response::fail(Lang::get('admin.recurring.interval_invalid'), 422);
            return null;
        }

        $startDate = trim((string) $request->input('start_date', ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            Response::fail(Lang::get('admin.recurring.start_date_invalid'), 422);
            return null;
        }

        $endDate = trim((string) $request->input('end_date', ''));
        if ($endDate !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || $endDate < $startDate)) {
            Response::fail(Lang::get('admin.recurring.end_date_invalid'), 422);
            return null;
        }

        $startTime = trim((string) $request->input('scheduled_start_time', ''));
        if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            Response::fail(Lang::get('admin.recurring.start_time_invalid'), 422);
            return null;
        }

        $description = trim((string) $request->input('description', ''));

        return [
            'project_id'           => $projectId,
            'assigned_worker_id'   => $workerId > 0 ? $workerId : null,
            'title'                => $title,
            'description'          => $description !== '' ? $description : null,
            'frequency'            => $frequency,
            'interval_count'       => $interval,
            'scheduled_start_time' => $startTime !== '' ? $startTime : null,
            'start_date'           => $startDate,
            'end_date'             => $endDate !== '' ? $endDate : null,
        ];
    }
}
