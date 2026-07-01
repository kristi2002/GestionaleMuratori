<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

final class AuthController
{
    /** GET /login — show the form (redirect to role home if already logged in). */
    public function show(Request $request): void
    {
        if (Auth::check()) {
            $home = Auth::homeFor(Auth::role());
            if (parse_url($home, PHP_URL_PATH) !== '/login') {
                Response::redirect($home);
                return;
            }
        }
        Response::html(View::render('auth/login', ['title' => 'Accesso'], 'layout'));
    }

    /** POST /login — AJAX; returns JSON with the redirect target on success. */
    public function login(Request $request): void
    {
        $email    = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === '' || $password === '') {
            Response::fail('Inserisci email e password.', 422);
            return;
        }

        $user = Auth::attempt($email, $password);
        if ($user === null) {
            Response::fail('Credenziali non valide.', 401);
            return;
        }

        Auth::login($user);
        Response::ok(['redirect' => Auth::homeFor($user['role'])]);
    }

    /** POST|GET /logout — clear session and return to login. */
    public function logout(Request $request): void
    {
        Auth::logout();
        Response::redirect(Url::to('/login'));
    }
}
