<?php
/**
 * HTTP end-to-end: subcontractor portal (v2 Phase 3). Login routing, portal
 * scoping (assigned projects only), and the no-cross-role / no-inventory rules.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

// ---------------------------------------------------------------------------
T::section('E2E: subcontractor portal');
$sub = new HttpClient($baseUrl);
$r = $sub->login('sub1@gestionale.local', 'password');
T::equals(200, $r['status'], 'subcontractor login ok');
T::ok(str_contains((string) ($r['json']['data']['redirect'] ?? ''), '/sub'), 'subcontractor redirected to /sub');

T::equals(200, $sub->get('/sub', ['json' => false])['status'], 'portal home renders');

// Seed assigns the subcontractor to project 1 only.
$assigned = (int) $pdo->query(
    "SELECT ps.project_id FROM project_subcontractors ps
     JOIN users u ON u.subcontractor_id = ps.subcontractor_id
     WHERE u.email = 'sub1@gestionale.local' LIMIT 1"
)->fetchColumn();
$other = (int) $pdo->query(
    "SELECT id FROM projects WHERE id NOT IN (
        SELECT ps.project_id FROM project_subcontractors ps
        JOIN users u ON u.subcontractor_id = ps.subcontractor_id
        WHERE u.email = 'sub1@gestionale.local'
     ) ORDER BY id LIMIT 1"
)->fetchColumn();

T::equals(200, $sub->get('/sub/projects/' . $assigned, ['json' => false])['status'], 'assigned project visible');
T::equals(404, $sub->get('/sub/projects/' . $other, ['json' => false])['status'], 'unassigned project hidden (404, no existence leak)');

// No inventory / cost exposure and no cross-role access.
T::equals(403, $sub->get('/admin', ['json' => false])['status'], 'subcontractor blocked from /admin');
T::equals(403, $sub->get('/admin/warehouse', ['json' => false])['status'], 'subcontractor blocked from warehouse');
T::equals(403, $sub->get('/worker', ['json' => false])['status'], 'subcontractor blocked from /worker');
T::equals(403, $sub->get('/client', ['json' => false])['status'], 'subcontractor blocked from /client');

// Other roles cannot reach the portal. (worker2 is never mutated by other cases.)
$worker = new HttpClient($baseUrl);
T::equals(200, $worker->login('worker2@gestionale.local', 'password')['status'], 'worker2 login ok');
T::equals(403, $worker->get('/sub', ['json' => false])['status'], 'worker blocked from /sub');

// ---------------------------------------------------------------------------
T::section('E2E: admin subcontractor management');
$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(200, $admin->get('/admin/subcontractors', ['json' => false])['status'], 'admin subcontractors page renders');

$r = $admin->post('/admin/subcontractors', ['name' => 'HTTP Sub Test', 'email' => 'httpsub@test.local']);
T::ok(($r['json']['ok'] ?? false) === true, 'admin can create a subcontractor');
$newId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($newId > 0, 'create returns an id');

$r = $admin->post('/admin/subcontractors', ['name' => '', 'email' => 'bad']);
T::equals(422, $r['status'], 'blank name rejected');

$r = $admin->post('/admin/subcontractors/' . $newId . '/projects', ['project_ids' => [$assigned]]);
T::ok(($r['json']['ok'] ?? false) === true, 'admin can assign projects to a subcontractor');
$cnt = (int) $pdo->query("SELECT COUNT(*) FROM project_subcontractors WHERE subcontractor_id = {$newId}")->fetchColumn();
T::equals(1, $cnt, 'assignment persisted');
