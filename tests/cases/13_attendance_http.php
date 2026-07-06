<?php
/**
 * HTTP end-to-end: Badge di Cantiere clock in/out (v2 Phase 4a). Access control,
 * the single-open-attendance rule, GPS validation, and the admin register.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

// ---------------------------------------------------------------------------
T::section('E2E: attendance access control');
$anon = new HttpClient($baseUrl);
T::equals(302, $anon->get('/attendance', ['json' => false])['status'], 'anonymous /attendance redirects to login');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(403, $admin->get('/attendance', ['json' => false])['status'], 'admin has no field clock screen (403)');

// worker2 is never mutated by other cases.
$worker = new HttpClient($baseUrl);
T::equals(200, $worker->login('worker2@gestionale.local', 'password')['status'], 'worker2 login ok');
T::equals(200, $worker->get('/attendance', ['json' => false])['status'], 'worker sees the clock screen');

// ---------------------------------------------------------------------------
T::section('E2E: attendance clock in/out flow');
$project = (int) $pdo->query("SELECT id FROM projects WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();

// Clock out with nothing open is rejected.
T::equals(422, $worker->post('/attendance/out', [])['status'], 'clock out with no open attendance rejected');

// Clock in (with GPS).
$r = $worker->post('/attendance/in', ['project_id' => $project, 'lat' => '43.31', 'lng' => '13.52']);
T::ok(($r['json']['ok'] ?? false) === true, 'worker can clock in');
$rowId = (int) $pdo->query(
    "SELECT id FROM site_attendance WHERE user_id = (SELECT id FROM users WHERE email='worker2@gestionale.local')
     ORDER BY id DESC LIMIT 1"
)->fetchColumn();
$row = $pdo->query("SELECT * FROM site_attendance WHERE id = {$rowId}")->fetch();
T::equals($project, (int) $row['project_id'], 'attendance recorded on the chosen project');
T::ok($row['entry_lat'] !== null, 'GPS latitude stored');
T::ok($row['exit_at'] === null, 'attendance is open after clock in');

// Double clock in is rejected (single open enforced).
T::equals(422, $worker->post('/attendance/in', ['project_id' => $project])['status'], 'second clock in rejected while already on site');

// Invalid GPS is rejected.
T::equals(422, $worker->post('/attendance/out', ['lat' => '999', 'lng' => '0'])['status'], 'out-of-range GPS rejected');

// Clock out succeeds.
$r = $worker->post('/attendance/out', ['lat' => '43.311', 'lng' => '13.521']);
T::ok(($r['json']['ok'] ?? false) === true, 'worker can clock out');
$row = $pdo->query("SELECT * FROM site_attendance WHERE id = {$rowId}")->fetch();
T::ok($row['exit_at'] !== null, 'exit time recorded');

// ---------------------------------------------------------------------------
T::section('E2E: subcontractor attendance is scoped to assigned projects');
$sub = new HttpClient($baseUrl);
$sub->login('sub1@gestionale.local', 'password');
$assigned = (int) $pdo->query(
    "SELECT ps.project_id FROM project_subcontractors ps
     JOIN users u ON u.subcontractor_id = ps.subcontractor_id
     WHERE u.email = 'sub1@gestionale.local' LIMIT 1"
)->fetchColumn();
$unassigned = (int) $pdo->query(
    "SELECT id FROM projects WHERE status='active' AND id NOT IN (
        SELECT ps.project_id FROM project_subcontractors ps
        JOIN users u ON u.subcontractor_id = ps.subcontractor_id
        WHERE u.email = 'sub1@gestionale.local') ORDER BY id LIMIT 1"
)->fetchColumn();
T::equals(422, $sub->post('/attendance/in', ['project_id' => $unassigned])['status'], 'subcontractor cannot clock into an unassigned project');
$r = $sub->post('/attendance/in', ['project_id' => $assigned]);
T::ok(($r['json']['ok'] ?? false) === true, 'subcontractor can clock into an assigned project');
$subRow = $pdo->query(
    "SELECT subcontractor_id FROM site_attendance
     WHERE user_id = (SELECT id FROM users WHERE email='sub1@gestionale.local') ORDER BY id DESC LIMIT 1"
)->fetch();
T::ok((int) $subRow['subcontractor_id'] > 0, 'subcontractor attendance records the company id');
$sub->post('/attendance/out', []);

// ---------------------------------------------------------------------------
T::section('E2E: admin attendance register');
T::equals(200, $admin->get('/admin/attendance?project_id=' . $project, ['json' => false])['status'], 'admin register renders');
$body = $admin->get('/admin/attendance?project_id=' . $project, ['json' => false])['body'];
T::ok(str_contains($body, 'Giuseppe Muratore'), 'register lists the clocked worker by name');
