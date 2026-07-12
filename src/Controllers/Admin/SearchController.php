<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Services\SearchService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/** Global admin search results page (GET /admin/search?q=). */
final class SearchController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $q = trim((string) $request->input('q', ''));

        Response::html(View::render('admin/search/index', [
            'title'   => Lang::get('admin.search.title'),
            'q'       => $q,
            'results' => $q !== '' ? (new SearchService())->search($q) : [],
        ], 'layout'));
    }
}
