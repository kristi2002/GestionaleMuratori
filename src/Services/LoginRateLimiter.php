<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Database;

/**
 * Sliding-window login throttling backed by the login_attempts table (which
 * doubles as an auth audit trail). Blocks when the same email — or the same
 * source IP across emails — accumulates too many failures inside the window.
 * A successful login clears the failure history for that email.
 */
final class LoginRateLimiter
{
    private int $maxPerEmail;
    private int $maxPerIp;
    private int $windowMinutes;

    public function __construct()
    {
        $this->maxPerEmail   = max(1, (int) Config::get('auth.max_attempts_email', 5));
        $this->maxPerIp      = max(1, (int) Config::get('auth.max_attempts_ip', 20));
        $this->windowMinutes = max(1, (int) Config::get('auth.window_minutes', 15));
    }

    public function tooManyAttempts(string $email, string $ip): bool
    {
        return $this->failuresFor('email', $email) >= $this->maxPerEmail
            || $this->failuresFor('ip', $ip) >= $this->maxPerIp;
    }

    public function record(string $email, string $ip, bool $succeeded): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO login_attempts (email, ip, succeeded) VALUES (?, ?, ?)'
        );
        $stmt->execute([$email, $ip, $succeeded ? 1 : 0]);

        if ($succeeded) {
            // Reset the counter so a legitimate user is not locked out by old noise.
            $clear = Database::pdo()->prepare(
                'DELETE FROM login_attempts WHERE email = ? AND succeeded = 0'
            );
            $clear->execute([$email]);
        }
    }

    private function failuresFor(string $column, string $value): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE {$column} = ? AND succeeded = 0
               AND attempted_at > DATE_SUB(NOW(), INTERVAL {$this->windowMinutes} MINUTE)"
        );
        $stmt->execute([$value]);
        return (int) $stmt->fetchColumn();
    }
}
