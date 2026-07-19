<?php
/**
 * Two-factor auth (migration 030): TOTP crypto (RFC 6238 vectors), one-time
 * recovery codes, and the users.totp_* toggle. In-process; cleans up.
 */
declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserRecoveryCodeModel;
use App\Support\Totp;

/** @var PDO $pdo */

T::section('Two-factor: TOTP crypto');

$sec = Totp::base32Encode('12345678901234567890');
T::equals('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $sec, 'base32 encodes the RFC seed');
T::equals('287082', Totp::code($sec, 59), 'TOTP matches RFC 6238 at T=59');
T::equals('081804', Totp::code($sec, 1111111109), 'TOTP matches RFC 6238 at T=1111111109');
T::ok(Totp::verify($sec, '287082', 59), 'verify accepts the right code in-window');
T::ok(!Totp::verify($sec, '287082', 59 + 120), 'verify rejects an out-of-window code');
T::ok(!Totp::verify($sec, '000000', 59), 'verify rejects a wrong code');
T::equals('hello world', Totp::base32Decode(Totp::base32Encode('hello world')), 'base32 round-trips');

T::section('Two-factor: recovery codes + toggle');

$uid = (int) $pdo->query("SELECT id FROM users WHERE role = 'worker' ORDER BY id LIMIT 1")->fetchColumn();
$rm  = new UserRecoveryCodeModel();
$rm->replaceForUser($uid, [UserRecoveryCodeModel::hash('code-one'), UserRecoveryCodeModel::hash('code-two')]);
T::equals(2, $rm->countUnused($uid), 'two recovery codes stored');
T::ok($rm->consume($uid, 'CODE-ONE'), 'recovery code consumed (case/format-insensitive)');
T::ok(!$rm->consume($uid, 'code-one'), 'the same recovery code cannot be reused');
T::equals(1, $rm->countUnused($uid), 'one recovery code left');
$rm->deleteForUser($uid);
T::equals(0, $rm->countUnused($uid), 'recovery codes cleared');

$um = new UserModel();
$um->enableTotp($uid, $sec);
T::equals(1, (int) $pdo->query("SELECT totp_enabled FROM users WHERE id = {$uid}")->fetchColumn(), 'totp enabled flag set');
$um->disableTotp($uid);
$row = $pdo->query("SELECT totp_enabled, totp_secret FROM users WHERE id = {$uid}")->fetch();
T::ok((int) $row['totp_enabled'] === 0 && $row['totp_secret'] === null, 'disable clears the flag + secret');
