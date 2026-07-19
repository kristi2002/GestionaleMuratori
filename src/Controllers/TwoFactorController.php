<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Http\Middleware\AuthGuard;
use App\Models\UserModel;
use App\Models\UserRecoveryCodeModel;
use App\Support\AuditLog;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Totp;
use App\Support\Url;
use App\Support\View;

/**
 * Two-factor (TOTP) self-service for any authenticated user: show status, enable
 * (scan a secret, confirm a code, receive one-time recovery codes), or disable
 * (re-confirm the password). Plain-form POSTs with post-redirect-get; freshly
 * generated recovery codes are shown once via a session flash.
 */
final class TwoFactorController
{
    public function index(Request $request): void
    {
        $user = AuthGuard::require($request);
        $row  = (new UserModel())->findById((int) $user['id']);
        $enabled = (int) ($row['totp_enabled'] ?? 0) === 1;

        $data = [
            'title'   => Lang::get('auth.mfa_title'),
            'enabled' => $enabled,
            'error'   => $this->errorFor((string) $request->input('err', '')),
        ];

        if ($enabled) {
            $data['recoveryCount'] = (new UserRecoveryCodeModel())->countUnused((int) $user['id']);
            $flash = Session::get('flash_recovery');
            if (is_array($flash)) {
                $data['recoveryCodes'] = $flash;
                Session::forget('flash_recovery');
            }
        } else {
            $secret = Session::get('pending_totp');
            if (!is_string($secret) || $secret === '') {
                $secret = Totp::generateSecret();
                Session::set('pending_totp', $secret);
            }
            $data['secret']        = $secret;
            $data['secretGrouped'] = trim(chunk_split($secret, 4, ' '));
            $data['otpauth']       = Totp::otpauthUri(
                $secret,
                (string) ($row['email'] ?? $row['name'] ?? 'user'),
                (string) Config::get('app.name', 'Gestionale Muratori')
            );
        }

        Response::html(View::render('auth/twofactor', $data, 'layout'));
    }

    /** POST /2fa/enable — confirm a code against the pending secret, then turn 2FA on. */
    public function enable(Request $request): void
    {
        $user   = AuthGuard::require($request);
        $secret = Session::get('pending_totp');
        if (!is_string($secret) || $secret === '') {
            Response::redirect(Url::to('/2fa'));
            return;
        }
        if (!Totp::verify($secret, (string) $request->input('code', ''))) {
            Response::redirect(Url::to('/2fa?err=code'));
            return;
        }

        (new UserModel())->enableTotp((int) $user['id'], $secret);

        $codes = $this->generateRecoveryCodes();
        (new UserRecoveryCodeModel())->replaceForUser(
            (int) $user['id'],
            array_map(static fn (string $c): string => UserRecoveryCodeModel::hash($c), $codes)
        );

        Session::forget('pending_totp');
        Session::set('flash_recovery', $codes);
        AuditLog::record('updated', 'user', (int) $user['id'], '2FA enabled');

        Response::redirect(Url::to('/2fa'));
    }

    /** POST /2fa/disable — re-confirm the password, then turn 2FA off. */
    public function disable(Request $request): void
    {
        $user = AuthGuard::require($request);
        $row  = (new UserModel())->findById((int) $user['id']);
        if ($row === null || !password_verify((string) $request->input('password', ''), (string) $row['password_hash'])) {
            Response::redirect(Url::to('/2fa?err=pass'));
            return;
        }

        (new UserModel())->disableTotp((int) $user['id']);
        (new UserRecoveryCodeModel())->deleteForUser((int) $user['id']);
        AuditLog::record('updated', 'user', (int) $user['id'], '2FA disabled');

        Response::redirect(Url::to('/2fa'));
    }

    /** @return array<int,string> "xxxx-xxxx" one-time codes */
    private function generateRecoveryCodes(int $n = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $codes[] = bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2));
        }
        return $codes;
    }

    private function errorFor(string $err): ?string
    {
        return match ($err) {
            'code' => Lang::get('auth.mfa_invalid'),
            'pass' => Lang::get('auth.current_password_wrong'),
            default => null,
        };
    }
}
