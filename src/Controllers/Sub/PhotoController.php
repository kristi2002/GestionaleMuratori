<?php
declare(strict_types=1);

namespace App\Controllers\Sub;

use App\Http\Middleware\AuthGuard;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
use App\Models\ProjectSubcontractorModel;
use App\Services\PhotoStreamService;
use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Permission-checked photo streaming for the subcontractor portal.
 * Ownership chain: photo -> intervention -> project must be assigned to the
 * subcontractor (project_subcontractors). Photos are never static files (§ security).
 */
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

    private function stream(Request $request, string $id, bool $original): void
    {
        AuthGuard::require($request, ['subcontractor']);

        $photo = (new PhotoModel())->find((int) $id);
        if ($photo === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $intervention = (new InterventionModel())->find((int) $photo['intervention_id']);
        if ($intervention === null
            || !(new ProjectSubcontractorModel())->isAssigned(
                (int) Auth::subcontractorId(),
                (int) $intervention['project_id']
            )) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        if (!(new PhotoStreamService())->streamPhoto($photo, $original)) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
        }
    }
}
