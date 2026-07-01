<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

/**
 * Server-side RBAC gate (§6). Call at the top of every protected action.
 * Stops the request (exit) on failure; returns the user snapshot on success.
 *
 * @param string[] $roles Allowed roles; empty = any authenticated user.
 */
final class AuthGuard
{
    public static function require(Request $request, array $roles = []): array
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                Response::fail('Sessione scaduta. Effettua di nuovo l\'accesso.', 401);
            } else {
                Response::redirect(Url::to('/login'));
            }
            exit;
        }

        $user = Auth::user();
        if ($roles !== [] && !in_array($user['role'], $roles, true)) {
            if ($request->wantsJson()) {
                Response::fail('Accesso negato.', 403);
            } else {
                Response::html(View::render('errors/403', ['title' => 'Accesso negato'], 'layout'), 403);
            }
            exit;
        }

        return $user;
    }
}
