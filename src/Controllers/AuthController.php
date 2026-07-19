<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use App\Models\UserRecoveryCodeModel;
use App\Services\LoginRateLimiter;
use App\Support\Auth;
use App\Support\Totp;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Mailer;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;
use DateTimeImmutable;

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
        if ($user === null) {
            $limiter->record($email, $ip, false);
            Response::fail(Lang::get('auth.invalid_credentials'), 401);
            return;
        }

        // Two-factor second step — only for accounts with TOTP enabled (others log
        // in exactly as before). A correct password with a missing code is not a
        // failed attempt; a wrong code is (so it counts toward the rate limiter).
        if ((int) ($user['totp_enabled'] ?? 0) === 1) {
            $code = trim((string) $request->input('code', ''));
            if ($code === '') {
                Response::json(['ok' => false, 'error' => Lang::get('auth.mfa_required'), 'mfa_required' => true], 401);
                return;
            }
            $ok = Totp::verify((string) ($user['totp_secret'] ?? ''), $code)
                || (new UserRecoveryCodeModel())->consume((int) $user['id'], $code);
            if (!$ok) {
                $limiter->record($email, $ip, false);
                Response::fail(Lang::get('auth.mfa_invalid'), 401);
                return;
            }
        }

        $limiter->record($email, $ip, true);
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

    /** GET /forgot-password — request-a-reset form. */
    public function showForgot(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::homeFor(Auth::role()));
            return;
        }
        Response::html(View::render('auth/forgot', ['title' => Lang::get('auth.forgot_title')], 'layout'));
    }

    /**
     * POST /forgot-password — issue a reset token and e-mail it. Always shows the
     * same generic confirmation (no account enumeration).
     */
    public function sendForgot(Request $request): void
    {
        $email = trim((string) $request->input('email', ''));
        $user  = $email !== '' ? (new UserModel())->findByEmail($email) : null;

        if ($user !== null && (int) $user['is_active'] === 1) {
            $resets = new PasswordResetModel();
            $resets->deleteForUser((int) $user['id']);
            $token = bin2hex(random_bytes(32));
            $resets->create(
                (int) $user['id'],
                hash('sha256', $token),
                (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s')
            );
            $this->sendResetEmail((string) $user['email'], (string) $user['name'], $token);
        }

        Response::html(View::render('auth/forgot', [
            'title' => Lang::get('auth.forgot_title'),
            'sent'  => true,
        ], 'layout'));
    }

    /** GET /reset-password?token= — new-password form (validates the token). */
    public function showReset(Request $request): void
    {
        $token = (string) $request->input('token', '');
        $valid = $token !== '' && (new PasswordResetModel())->findValid(hash('sha256', $token)) !== null;

        Response::html(View::render('auth/reset', [
            'title' => Lang::get('auth.reset_title'),
            'token' => $token,
            'valid' => $valid,
        ], 'layout'));
    }

    /** POST /reset-password — set the new password if the token is valid. */
    public function doReset(Request $request): void
    {
        $token   = (string) $request->input('token', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('new_password_confirm', '');

        $resets = new PasswordResetModel();
        $row    = $token !== '' ? $resets->findValid(hash('sha256', $token)) : null;

        $error = null;
        if ($row === null) {
            $error = Lang::get('auth.reset_invalid');
        } elseif (strlen($new) < self::MIN_PASSWORD_LENGTH) {
            $error = sprintf(Lang::get('auth.password_too_short'), self::MIN_PASSWORD_LENGTH);
        } elseif ($new !== $confirm) {
            $error = Lang::get('auth.password_mismatch');
        }

        if ($error !== null) {
            Response::html(View::render('auth/reset', [
                'title' => Lang::get('auth.reset_title'),
                'token' => $token,
                'valid' => $row !== null,
                'error' => $error,
            ], 'layout'));
            return;
        }

        (new UserModel())->updatePassword((int) $row['user_id'], password_hash($new, PASSWORD_DEFAULT));
        $resets->markUsed((int) $row['id']);

        Response::html(View::render('auth/reset', [
            'title' => Lang::get('auth.reset_title'),
            'done'  => true,
        ], 'layout'));
    }

    private function sendResetEmail(string $to, string $name, string $token): void
    {
        $link = rtrim((string) Config::get('app.url', ''), '/') . Url::to('/reset-password?token=' . $token);
        $body = '<p>' . View::e(sprintf(Lang::get('auth.reset_email_greeting'), $name)) . '</p>'
              . '<p>' . View::e(Lang::get('auth.reset_email_body')) . '</p>'
              . '<p><a href="' . View::e($link) . '">' . View::e($link) . '</a></p>'
              . '<p>' . View::e(Lang::get('auth.reset_email_expiry')) . '</p>';
        Mailer::send($to, Lang::get('auth.reset_email_subject'), $body);
    }
}
