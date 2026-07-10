<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Services\PhotoStreamService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class PhotoController
{
    public function show(Request $request, string $id): void
    {
        $this->stream($request, $id, true);
    }

    public function thumb(Request $request, string $id): void
    {
        $this->stream($request, $id, false);
    }

    /** Ownership chain: photo -> intervention -> project.client_id = session.client_id (§6). */
    private function stream(Request $request, string $id, bool $original): void
    {
        AuthGuard::require($request, ['client']);

        $photo = (new PhotoModel())->find((int) $id);
        if ($photo === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $intervention = (new InterventionModel())->find((int) $photo['intervention_id']);
        if ($intervention === null || (int) $intervention['project_client_id'] !== (int) Auth::clientId()) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        // Clients only ever see before/after evidence; 'during' progress photos are
        // worker-internal — the gallery hides them, so enforce it on the stream too
        // (otherwise a client could view one by guessing its id).
        if (($photo['type'] ?? '') === 'during') {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        if (!(new PhotoStreamService())->streamPhoto($photo, $original)) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
        }
    }
}
