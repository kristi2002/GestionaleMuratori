<?php
/**
 * Badge di Cantiere (v2 Phase 4a): the site-attendance ledger — clock in creates an
 * open row, clock out closes it, and only one row per user stays open at a time.
 * Uses a fresh project/user pairing on the seeded DB.
 */
declare(strict_types=1);

use App\Models\SiteAttendanceModel;

/** @var PDO $pdo (from run.php) */

$att = new SiteAttendanceModel();

// worker2 (id 3) has no seeded attendance; project 1 exists.
$USER    = 3;
$PROJECT = 1;

// ---------------------------------------------------------------------------
T::section('Attendance: clock in / out');
T::ok($att->openForUser($USER) === null, 'no open attendance before clocking in');

$id = $att->clockIn([
    'project_id' => $PROJECT, 'user_id' => $USER, 'subcontractor_id' => null,
    'person_name' => 'Giuseppe Muratore', 'entry_at' => date('Y-m-d H:i:s'),
    'entry_lat' => '43.3000000', 'entry_lng' => '13.5000000',
]);
T::ok($id > 0, 'clockIn returns an id');

$open = $att->openForUser($USER);
T::ok($open !== null, 'openForUser now returns the open row');
T::equals($PROJECT, (int) ($open['project_id'] ?? 0), 'open row is on the right project');
T::ok($open['exit_at'] === null, 'the open row has no exit yet');
T::ok($att->countPresent($PROJECT) >= 1, 'countPresent sees the on-site worker');

$att->clockOut((int) $id, date('Y-m-d H:i:s'), '43.3000001', '13.5000001');
T::ok($att->openForUser($USER) === null, 'openForUser is null again after clock out');

$closed = $att->find((int) $id);
T::ok($closed['exit_at'] !== null, 'the row now has an exit time');
T::ok($closed['exit_lat'] !== null, 'the exit coordinates were stored');

// ---------------------------------------------------------------------------
T::section('Attendance: clock out is idempotent (only closes an open row)');
$second = $att->clockOut((int) $id, date('Y-m-d H:i:s'), null, null);
// The UPDATE runs but matches no open row; exit_at stays as first set.
$still = $att->find((int) $id);
T::ok($still['exit_at'] === $closed['exit_at'], 're-clocking out does not overwrite the first exit time');

// ---------------------------------------------------------------------------
T::section('Attendance: project register');
$rows = $att->forProject($PROJECT, date('Y-m-d'));
T::ok(count($rows) >= 1, 'forProject returns today\'s attendance rows');
$mine = array_values(array_filter($rows, static fn ($r) => (int) $r['id'] === (int) $id));
T::equals(1, count($mine), 'the register includes the closed row');
