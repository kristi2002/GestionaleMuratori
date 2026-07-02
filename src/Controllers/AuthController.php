<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\UserModel;
use App\Services\LoginRateLimiter;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

final class AuthController
{
    public const MIN_PASSWORD_LENGTH = 8;

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
            Response::fail(Lang::get('auth.credentials_required'), 422);
            return;
        }

        $ip      = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $limiter = new LoginRateLimiter();
        if ($limiter->tooManyAttempts($email, $ip)) {
            Response::fail(Lang::get('auth.too_many_attempts'), 429);
            return;
        }

        $user = Auth::attempt($email, $password);
        $limiter->record($email, $ip, $user !== null);

        if ($user === null) {
            Response::fail(Lang::get('auth.invalid_credentials'), 401);
            return;
        }

        Auth::login($user);
        Response::ok(['redirect' => Auth::homeFor($user['role'])]);
    }

    /** POST /logout — clear session and return to login. */
    public function logout(Request $request): void
    {
        Auth::logout();
        if ($request->wantsJson()) {
            Response::ok(['redirect' => Url::to('/login')]);
            return;
        }
        Response::redirect(Url::to('/login'));
    }

    /** GET /password — change-password page for any authenticated role. */
    public function showPassword(Request $request): void
    {
        AuthGuard::require($request);
        Response::html(View::render('auth/password', ['title' => Lang::get('auth.password_title')], 'layout'));
    }

    /** POST /password — verify current password, set the new one. */
    public function changePassword(Request $request): void
    {
        $user = AuthGuard::require($request);

        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirm', '');

        $model = new UserModel();
        $row   = $model->findById((int) $user['id']);
        if ($row === null || !password_verify($current, (string) $row['password_hash'])) {
            Response::fail(Lang::get('auth.current_password_wrong'), 422);
            return;
        }
        if (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            Response::fail(sprintf(Lang::get('auth.password_too_short'), self::MIN_PASSWORD_LENGTH), 422);
            return;
        }
        if ($new !== $confirm) {
            Response::fail(Lang::get('auth.password_mismatch'), 422);
            return;
        }

        $model->updatePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT));
        Response::ok();
    }
}
