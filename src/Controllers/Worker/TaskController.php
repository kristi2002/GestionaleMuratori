<?php
declare(strict_types=1);

namespace App\Controllers\Worker;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\InterventionOwnerGuard;
use App\Models\InterventionMaterialModel;
use App\Models\InterventionModel;
use App\Models\InterventionTaskModel;
use App\Models\InterventionTimeEntryModel;
use App\Models\PhotoModel;
use App\Services\InterventionService;
use App\Services\PhotoStreamService;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\Storage;
use App\Support\View;
use RuntimeException;

final class TaskController
{
    /** Status buttons the worker UI exposes directly; 'completed' goes through complete(). */
    private const QUICK_TRANSITIONS = ['in_progress', 'on_hold', 'cancelled'];

    /** Tabs of the worker task list (gap F4): today (default), upcoming, done. */
    private const TABS = ['today', 'upcoming', 'done'];

    /** GET /worker — "My Tasks" (§7 phase 5 + F4 tabs; default: scheduled today, assigned to me). */
    public function today(Request $request): void
    {
        AuthGuard::require($request, ['worker']);

        $tab = (string) $request->input('tab', 'today');
        if (!in_array($tab, self::TABS, true)) {
            $tab = 'today';
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $interventions = (new InterventionModel())->forWorkerTab((int) Auth::id(), $tab, $today);

        // Attach checklist progress in one batched query (no N+1).
        $progress = (new InterventionTaskModel())->progressForInterventions(
            array_map(static fn (array $i): int => (int) $i['id'], $interventions)
        );
        foreach ($interventions as &$iv) {
            $iv['task_progress'] = $progress[(int) $iv['id']] ?? ['done' => 0, 'total' => 0];
        }
        unset($iv);

        Response::html(View::render('worker/today', [
            'title'         => Lang::get('worker.today_title'),
            'interventions' => $interventions,
            'tab'           => $tab,
        ], 'layout'));
    }

    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $materials = (new InterventionMaterialModel())->forIntervention((int) $id);
        $photos    = (new PhotoModel())->forIntervention((int) $id);
        $photosByType = ['before' => [], 'during' => [], 'after' => []];
        foreach ($photos as $photo) {
            $photosByType[$photo['type']][] = $photo;
        }

        $timeModel = new InterventionTimeEntryModel();
        $running   = $timeModel->runningForUser((int) Auth::id());
        $timerHere = ($running !== null && (int) $running['intervention_id'] === (int) $id) ? $running : null;

        Response::html(View::render('worker/show', [
            'title'           => $intervention['title'],
            'intervention'    => $intervention,
            'materials'       => $materials,
            'photosByType'    => $photosByType,
            'tasks'           => (new InterventionTaskModel())->forIntervention((int) $id),
            'timerHere'       => $timerHere,
            'timerOtherTitle' => ($running !== null && $timerHere === null) ? (string) $running['intervention_title'] : null,
            'timeTotal'       => $timeModel->totalSeconds((int) $id),
            'timerElapsed'    => $timerHere !== null ? $timeModel->elapsedSeconds((int) $timerHere['id']) : 0,
        ], 'layout'));
    }

    /** POST /worker/interventions/{id}/timer/start — start a work timer on this job. */
    public function startTimer(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $model   = new InterventionTimeEntryModel();
        $running = $model->runningForUser((int) Auth::id());
        if ($running !== null) {
            if ((int) $running['intervention_id'] === (int) $id) {
                Response::ok(); // already timing this job — no-op
                return;
            }
            Response::fail(sprintf(Lang::get('worker.timer_busy'), (string) $running['intervention_title']), 422);
            return;
        }

        $model->start((int) $id, (int) Auth::id());
        Response::ok();
    }

    /** POST /worker/interventions/{id}/timer/stop — stop the running timer on this job. */
    public function stopTimer(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        (new InterventionTimeEntryModel())->stop((int) $id, (int) Auth::id());
        Response::ok();
    }

    /** POST /worker/interventions/{id}/tasks/{taskId}/toggle — tick a checklist item. */
    public function toggleTask(Request $request, string $id, string $taskId): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $model = new InterventionTaskModel();
        $task  = $model->find((int) $taskId);
        if ($task === null || (int) $task['intervention_id'] !== (int) $id) {
            Response::fail(Lang::get('worker.not_found'), 404);
            return;
        }

        $model->setDone((int) $taskId, (int) $request->input('done', 0) === 1, (int) Auth::id());
        Response::ok();
    }

    public function status(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $toStatus = (string) $request->input('to_status', '');
        if (!in_array($toStatus, self::QUICK_TRANSITIONS, true)) {
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

    /** POST /worker/interventions/{id}/complete — §4.2 commit + §4.4 gate. */
    public function complete(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $qtyUsedByMaterialId = [];
        foreach ((array) $request->input('qty_used', []) as $materialId => $value) {
            $qtyUsedByMaterialId[(int) $materialId] = trim((string) $value);
        }

        $notes = trim((string) $request->input('completion_notes', ''));

        try {
            (new InterventionService())->complete(
                (int) $id,
                (int) Auth::id(),
                $qtyUsedByMaterialId,
                $notes !== '' ? $notes : null
            );
            Response::ok();
        } catch (RuntimeException $e) {
            Response::fail($e->getMessage(), 422);
        }
    }

    /** POST /worker/interventions/{id}/signature — canvas PNG captured client-side as a data URL. */
    public function saveSignature(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        $dataUrl = (string) $request->input('signature', '');
        $prefix  = 'data:image/png;base64,';
        if (!str_starts_with($dataUrl, $prefix)) {
            Response::fail(Lang::get('worker.signature_empty'), 422);
            return;
        }

        $binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($binary === false || $binary === '' || strlen($binary) > 5 * 1024 * 1024) {
            Response::fail(Lang::get('worker.signature_empty'), 422);
            return;
        }

        $relPath = $intervention['project_id'] . '/' . $id . '/signature.png';
        (Storage::disk())->put($relPath, $binary);
        (new InterventionModel())->setSignaturePath((int) $id, $relPath);

        Response::ok();
    }

    /** GET /worker/interventions/{id}/signature — streams the saved signature PNG. */
    public function signature(Request $request, string $id): void
    {
        AuthGuard::require($request, ['worker']);
        $intervention = InterventionOwnerGuard::require($request, $id, (int) Auth::id());
        if ($intervention === null) {
            return;
        }

        if (!(new PhotoStreamService())->streamFile($intervention['client_signature_path'])) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
        }
    }
}
