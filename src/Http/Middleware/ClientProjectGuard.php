<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ProjectModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * §6 RBAC — a client only ever sees "WHERE projects.client_id = session.client_id".
 * Missing and not-mine are both reported as 404 (no existence leak, same pattern
 * as InterventionOwnerGuard for the worker app).
 */
final class ClientProjectGuard
{
    public static function require(Request $request, string $projectId, int $clientId): ?array
    {
        $project = (new ProjectModel())->find((int) $projectId);
        if ($project === null || (int) $project['client_id'] !== $clientId) {
            if ($request->wantsJson()) {
                Response::fail(Lang::get('client.not_found'), 404);
            } else {
                Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            }
            return null;
        }
        return $project;
    }
}
