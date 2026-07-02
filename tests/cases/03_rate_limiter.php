<?php
/** LoginRateLimiter: sliding window per email and per IP, reset on success. */
declare(strict_types=1);

use App\Services\LoginRateLimiter;

/** @var PDO $pdo (from run.php) */

T::section('Rate limiter');
$pdo->exec('DELETE FROM login_attempts');
$limiter = new LoginRateLimiter();

T::ok(!$limiter->tooManyAttempts('a@test.local', '10.0.0.1'), 'clean slate: not limited');

for ($i = 0; $i < 5; $i++) {
    $limiter->record('a@test.local', '10.0.0.1', false);
}
T::ok($limiter->tooManyAttempts('a@test.local', '10.0.0.1'), 'blocked after 5 failures (email)');
T::ok($limiter->tooManyAttempts('a@test.local', '10.0.0.99'), 'email block applies from any IP');
T::ok(!$limiter->tooManyAttempts('b@test.local', '10.0.0.2'), 'other email + other IP unaffected');

$limiter->record('a@test.local', '10.0.0.1', true);
T::ok(!$limiter->tooManyAttempts('a@test.local', '10.0.0.1'), 'success clears the failure history');

// IP-wide limit (default 20) across many emails.
for ($i = 0; $i < 20; $i++) {
    $limiter->record("bot{$i}@test.local", '10.9.9.9', false);
}
T::ok($limiter->tooManyAttempts('fresh@test.local', '10.9.9.9'), 'IP-wide block after 20 failures');

$pdo->exec('DELETE FROM login_attempts');
