<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

final class DashboardController
{
    /** GET / — send the user to their role landing page (or login). */
    public function home(Request $request): void
    {
        if (!Auth::check()) {
            Response::redirect(Url::to('/login'));
            return;
        }
        $home = Auth::homeFor(Auth::role());
        if (parse_url($home, PHP_URL_PATH) === '/login') {
            Auth::logout();
            Response::redirect(Url::to('/login'));
            return;
        }
        Response::redirect($home);
    }

    public function admin(Request $request): void
    {
        AuthGuard::require($request, ['admin']);
        Response::html(View::render('admin/dashboard', ['title' => 'Pannello Amministratore'], 'layout'));
    }

    /** GET /health — public lightweight readiness probe. */
    public function health(Request $request): void
    {
        try {
            Database::pdo()->query('SELECT 1');
            Response::ok(['status' => 'ok']);
        } catch (\Throwable $e) {
            Response::fail('Database non raggiungibile.', 500);
        }
    }
}
