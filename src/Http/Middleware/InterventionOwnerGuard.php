<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\InterventionModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * §6 RBAC — "a worker can only transition interventions assigned to them."
 * Missing and not-assigned-to-me are both reported as 404 (no existence leak).
 */
final class InterventionOwnerGuard
{
    public static function require(Request $request, string $interventionId, int $workerId): ?array
    {
        $intervention = (new InterventionModel())->find((int) $interventionId);
        if ($intervention === null || (int) ($intervention['assigned_worker_id'] ?? 0) !== $workerId) {
            if ($request->wantsJson()) {
                Response::fail(Lang::get('worker.not_found'), 404);
            } else {
                Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            }
            return null;
        }
        return $intervention;
    }
}
