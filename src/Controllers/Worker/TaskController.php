<?php
declare(strict_types=1);

namespace App\Controllers\Worker;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\InterventionOwnerGuard;
use App\Models\InterventionMaterialModel;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Services\InterventionService;
use App\Services\PhotoStreamService;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\LocalStorage;
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

        Response::html(View::render('worker/show', [
            'title'        => $intervention['title'],
            'intervention' => $intervention,
            'materials'    => $materials,
            'photosByType' => $photosByType,
        ], 'layout'));
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
        (new LocalStorage((string) Config::get('storage.uploads_path')))->put($relPath, $binary);
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
