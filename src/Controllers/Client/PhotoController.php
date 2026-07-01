<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\LocalStorage;
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

        $storage = new LocalStorage((string) Config::get('storage.uploads_path'));
        $relPath = $original ? $photo['file_path'] : ($photo['thumb_path'] ?? $photo['file_path']);
        if (!$storage->exists($relPath)) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        header('Content-Type: ' . (str_ends_with($relPath, '.png') ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: private, max-age=86400');
        echo $storage->get($relPath);
    }
}
