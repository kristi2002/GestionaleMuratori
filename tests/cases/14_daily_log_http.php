<?php
/**
 * HTTP end-to-end: Giornale dei Lavori (v2 Phase 4b). Admin-only access, create +
 * duplicate guard, edit, equipment sync, and the closed-day read-only lock.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

// ---------------------------------------------------------------------------
T::section('E2E: Giornale access control');
$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
T::equals(403, $worker->get('/admin/daily-logs', ['json' => false])['status'], 'worker blocked from Giornale');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(200, $admin->get('/admin/daily-logs', ['json' => false])['status'], 'admin sees the Giornale');

// ---------------------------------------------------------------------------
T::section('E2E: create, duplicate guard, edit');
$project = 3; // active; distinct from other cases' project usage
$date    = '2026-06-15';

$r = $admin->post('/admin/daily-logs', [
    'project_id' => $project, 'log_date' => $date,
    'workers_present' => 4, 'work_done' => 'Armatura solaio',
]);
T::ok(($r['json']['ok'] ?? false) === true, 'admin creates a daily log');
$logId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($logId > 0, 'create returns a log id');

// Duplicate (same project+date) rejected.
T::equals(422, $admin->post('/admin/daily-logs', ['project_id' => $project, 'log_date' => $date])['status'], 'duplicate day rejected');
// Future date rejected.
T::equals(422, $admin->post('/admin/daily-logs', ['project_id' => $project, 'log_date' => '2099-01-01'])['status'], 'future date rejected');
// Non-numeric workers rejected.
T::equals(422, $admin->post('/admin/daily-logs', ['project_id' => $project, 'log_date' => '2026-06-16', 'workers_present' => 'x'])['status'], 'non-numeric workers rejected');

$r = $admin->post('/admin/daily-logs/' . $logId, ['workers_present' => 7, 'work_done' => 'Armatura + getto']);
T::ok(($r['json']['ok'] ?? false) === true, 'admin edits an open log');
$row = $pdo->query("SELECT * FROM daily_logs WHERE id = {$logId}")->fetch();
T::equals(7, (int) $row['workers_present'], 'edit persisted');

// ---------------------------------------------------------------------------
T::section('E2E: equipment + close lock');
$eq = (int) $pdo->query('SELECT id FROM equipment ORDER BY id LIMIT 1')->fetchColumn();
$r = $admin->post('/admin/daily-logs/' . $logId . '/equipment', ['equipment_ids' => [$eq]]);
T::ok(($r['json']['ok'] ?? false) === true, 'equipment attached to the log');
T::equals(1, (int) $pdo->query("SELECT COUNT(*) FROM daily_log_equipment WHERE daily_log_id = {$logId}")->fetchColumn(), 'equipment link persisted');

$r = $admin->post('/admin/daily-logs/' . $logId . '/close', []);
T::ok(($r['json']['ok'] ?? false) === true, 'admin closes the log');
T::equals(1, (int) $pdo->query("SELECT is_closed FROM daily_logs WHERE id = {$logId}")->fetchColumn(), 'log is closed');

// A closed log is read-only.
T::equals(422, $admin->post('/admin/daily-logs/' . $logId, ['work_done' => 'HACK'])['status'], 'editing a closed log rejected');
T::equals(422, $admin->post('/admin/daily-logs/' . $logId . '/equipment', ['equipment_ids' => []])['status'], 'changing equipment on a closed log rejected');
T::equals(422, $admin->post('/admin/daily-logs/' . $logId . '/close', [])['status'], 're-closing rejected');
$row = $pdo->query("SELECT work_done FROM daily_logs WHERE id = {$logId}")->fetch();
T::ok(!str_contains((string) $row['work_done'], 'HACK'), 'closed log content unchanged after rejected edit');
