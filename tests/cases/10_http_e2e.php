<?php
/**
 * HTTP end-to-end simulation against the running dev server (started by
 * run.php): security (CSRF, headers, rate limiting), RBAC matrix, admin CRUD,
 * user management, full intervention lifecycle with photo upload + signature +
 * completion gate, client portal scoping, and report downloads.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

$stockOf = static function (int $itemId) use ($pdo): float {
    $stmt = $pdo->prepare('SELECT qty_in_stock FROM warehouse_items WHERE id = ?');
    $stmt->execute([$itemId]);
    return (float) $stmt->fetchColumn();
};

$makePng = static function (): string {
    $path = sys_get_temp_dir() . '/gm-test-' . bin2hex(random_bytes(4)) . '.png';
    $im = imagecreatetruecolor(120, 90);
    imagefilledrectangle($im, 0, 0, 119, 89, imagecolorallocate($im, 40, 160, 80));
    imagepng($im, $path);
    imagedestroy($im);
    return $path;
};

// ---------------------------------------------------------------------------
T::section('E2E: security basics');
$anon = new HttpClient($baseUrl);

$r = $anon->get('/health');
T::equals(200, $r['status'], '/health responds 200');
T::ok(($r['json']['ok'] ?? false) === true, '/health reports ok');

$r = $anon->get('/', ['json' => false]);
T::equals(302, $r['status'], 'anonymous / redirects');
$r = $anon->get('/admin', ['json' => false]);
T::equals(302, $r['status'], 'anonymous /admin redirects to login');

$r = $anon->get('/login', ['json' => false]);
T::equals(200, $r['status'], 'login page renders');
T::ok(str_contains($r['headers'], 'X-Frame-Options: DENY'), 'X-Frame-Options header present');
T::ok(str_contains($r['headers'], 'Content-Security-Policy:'), 'CSP header present');
T::ok(str_contains($r['headers'], 'X-Content-Type-Options: nosniff'), 'nosniff header present');
T::ok(str_contains($r['body'], 'csrf-token'), 'CSRF meta tag present');
T::ok(!str_contains($r['body'], 'cdn.jsdelivr.net'), 'no CDN dependencies (self-hosted assets)');

$r = $anon->get('/assets/vendor/bootstrap.min.css', ['json' => false]);
T::equals(200, $r['status'], 'self-hosted bootstrap css served');

$noToken = new HttpClient($baseUrl);
$r = $noToken->post('/login', ['email' => 'admin@gestionale.local', 'password' => 'password']);
T::equals(403, $r['status'], 'POST without CSRF token rejected');

// ---------------------------------------------------------------------------
T::section('E2E: login + rate limiting');
$admin = new HttpClient($baseUrl);
$r = $admin->login('admin@gestionale.local', 'wrong-password');
T::equals(401, $r['status'], 'wrong password rejected');
$r = $admin->login('admin@gestionale.local', '');
T::equals(422, $r['status'], 'missing password rejected');
$r = $admin->login('admin@gestionale.local', 'password');
T::equals(200, $r['status'], 'admin login ok');
T::ok(str_contains((string) ($r['json']['data']['redirect'] ?? ''), '/admin'), 'admin redirected to /admin');

$bot = new HttpClient($baseUrl);
for ($i = 0; $i < 5; $i++) {
    $bot->login('ratelimit@test.local', 'nope');
}
$r = $bot->login('ratelimit@test.local', 'nope');
T::equals(429, $r['status'], '6th failed login blocked (429)');

// ---------------------------------------------------------------------------
T::section('E2E: RBAC matrix');
$worker1 = new HttpClient($baseUrl);
$r = $worker1->login('worker1@gestionale.local', 'password');
T::equals(200, $r['status'], 'worker1 login ok');
$client1 = new HttpClient($baseUrl);
$r = $client1->login('client1@gestionale.local', 'password');
T::equals(200, $r['status'], 'client1 login ok');

T::equals(403, $worker1->get('/admin', ['json' => false])['status'], 'worker blocked from /admin');
T::equals(403, $worker1->get('/admin/warehouse', ['json' => false])['status'], 'worker blocked from warehouse');
T::equals(403, $client1->get('/worker', ['json' => false])['status'], 'client blocked from /worker');
T::equals(403, $client1->get('/admin/users', ['json' => false])['status'], 'client blocked from /admin/users');
T::equals(200, $admin->get('/admin', ['json' => false])['status'], 'admin dashboard renders');
T::equals(200, $worker1->get('/worker', ['json' => false])['status'], 'worker home renders');
T::equals(200, $client1->get('/client', ['json' => false])['status'], 'client home renders');

$rStats = $admin->get('/admin/statistics', ['json' => false]);
T::equals(200, $rStats['status'], 'admin statistics page renders');
T::ok(str_contains((string) $rStats['body'], 'app-chart-donut'), 'statistics page includes charts');
T::ok(str_contains((string) $rStats['body'], 'app-linechart'), 'statistics has the line chart');
T::ok(str_contains((string) $rStats['body'], 'app-legend-pct'), 'donut legend shows percentages');
T::equals(403, $worker1->get('/admin/statistics', ['json' => false])['status'], 'worker blocked from statistics');
T::equals(403, $client1->get('/admin/statistics', ['json' => false])['status'], 'client blocked from statistics');

// Editable keyboard shortcuts (admin-only)
$rSc = $admin->get('/shortcuts', ['json' => false]);
T::equals(200, $rSc['status'], 'shortcuts page renders for admin');
T::ok(str_contains((string) $rSc['body'], 'js-shortcuts-form'), 'admin sees the shortcuts editor');

$rSave = $admin->post('/shortcuts', ['shortcuts' => ['clients' => 'x', 'projects' => 'p']]);
T::equals(200, $rSave['status'], 'valid shortcuts save ok');
T::ok(($rSave['json']['ok'] ?? false) === true, 'save returns ok');
T::equals('x', $rSave['json']['data']['shortcuts']['clients'] ?? null, 'custom key reflected in response');
T::ok(str_contains((string) $admin->get('/shortcuts', ['json' => false])['body'], 'value="X"'), 'saved key persists in the editor');

T::equals(422, $admin->post('/shortcuts', ['shortcuts' => ['clients' => 'p']])['status'], 'duplicate key rejected');
T::equals(422, $admin->post('/shortcuts', ['shortcuts' => ['clients' => 'g']])['status'], 'reserved key rejected');
T::equals(403, $worker1->post('/shortcuts', ['shortcuts' => ['clients' => 'x']])['status'], 'worker cannot save shortcuts');

$admin->post('/shortcuts', ['shortcuts' => []]); // reset to defaults

// Per-cantiere financials dashboard (admin-only)
$rFin = $admin->get('/admin/financials', ['json' => false]);
T::equals(200, $rFin['status'], 'admin financials page renders');
T::ok(str_contains((string) $rFin['body'], 'Andamento Economico'), 'financials page shows its title');
T::equals(403, $worker1->get('/admin/financials', ['json' => false])['status'], 'worker blocked from financials');
T::equals(403, $client1->get('/admin/financials', ['json' => false])['status'], 'client blocked from financials');

// Per-project financial summary on the project detail page
$rProj = $admin->get('/admin/projects/1', ['json' => false]);
T::equals(200, $rProj['status'], 'admin project detail renders');
T::ok(str_contains((string) $rProj['body'], 'Andamento Economico'), 'project detail shows the financial summary');
T::ok(str_contains((string) $rProj['body'], 'Interventi del cantiere'), 'project detail shows the interventions section');

// Promemoria (project notes) CRUD + ownership
$rNote  = $admin->post('/admin/projects/1/notes', ['body' => 'Ordinare cemento Rossi', 'due_date' => '2026-08-01']);
T::equals(200, $rNote['status'], 'note create ok');
$noteId = (int) ($rNote['json']['data']['id'] ?? 0);
T::ok($noteId > 0, 'note id returned');
T::ok(str_contains((string) $admin->get('/admin/projects/1', ['json' => false])['body'], 'Ordinare cemento Rossi'), 'note shown on the project page');
T::equals(200, $admin->post("/admin/projects/1/notes/{$noteId}/toggle")['status'], 'note toggle ok');
T::equals(422, $admin->post('/admin/projects/1/notes', ['body' => ''])['status'], 'empty note rejected');
T::equals(404, $admin->post("/admin/projects/2/notes/{$noteId}/delete")['status'], "can't touch another project's note");
T::equals(403, $worker1->post('/admin/projects/1/notes', ['body' => 'x'])['status'], 'worker cannot add notes');
T::equals(200, $admin->post("/admin/projects/1/notes/{$noteId}/delete")['status'], 'note delete ok');

// Global search
$rSearch = $admin->get('/admin/search?q=Rossi', ['json' => false]);
T::equals(200, $rSearch['status'], 'admin search renders');
T::ok(str_contains((string) $rSearch['body'], 'Rossi'), 'search returns matching results');
T::equals(403, $worker1->get('/admin/search?q=x', ['json' => false])['status'], 'worker blocked from search');

// Interventions calendar
$rCal = $admin->get('/admin/interventions/calendar', ['json' => false]);
T::equals(200, $rCal['status'], 'interventions calendar renders');
T::ok(str_contains((string) $rCal['body'], 'app-cal-grid'), 'calendar grid present');
T::ok(str_contains((string) $rCal['body'], 'app-cal-month-select'), 'calendar has the month picker dropdown');
T::equals(403, $worker1->get('/admin/interventions/calendar', ['json' => false])['status'], 'worker blocked from calendar');

// CSV exports
$rCsv = $admin->get('/admin/expenses/export', ['json' => false]);
T::equals(200, $rCsv['status'], 'expenses CSV export ok');
T::ok(stripos((string) $rCsv['headers'], 'text/csv') !== false, 'expenses export is text/csv');
T::ok(str_contains((string) $admin->get('/admin/expenses', ['json' => false])['body'], 'data-months'), 'spese filter wires the custom date picker');
T::equals(200, $admin->get('/admin/interventions/export', ['json' => false])['status'], 'interventions CSV export ok');
T::equals(403, $worker1->get('/admin/expenses/export', ['json' => false])['status'], 'worker blocked from CSV export');
T::equals(200, $admin->get('/admin/clients/export', ['json' => false])['status'], 'clients CSV export ok');
T::equals(200, $admin->get('/admin/projects/export', ['json' => false])['status'], 'projects CSV export ok');

// DURC / compliance gating: expired-doc subcontractor is flagged
$rSub = $admin->get('/admin/subcontractors', ['json' => false]);
T::equals(200, $rSub['status'], 'subcontractors page renders');
T::ok(str_contains((string) $rSub['body'], 'Scaduti'), 'subcontractor with expired docs is flagged');

// Paginated list pages render (incl. an out-of-range page)
T::equals(200, $admin->get('/admin/projects?page=2', ['json' => false])['status'], 'projects list paginates');
T::equals(200, $admin->get('/admin/clients?page=2', ['json' => false])['status'], 'clients list paginates');
T::equals(200, $admin->get('/admin/subcontractors?page=2', ['json' => false])['status'], 'subcontractors list paginates');
T::equals(200, $admin->get('/admin/warehouse?page=2', ['json' => false])['status'], 'warehouse list paginates');

// Self-service password reset
T::equals(200, $anon->get('/forgot-password', ['json' => false])['status'], 'forgot-password page renders');
T::equals(200, $admin->post('/forgot-password', ['email' => 'admin@gestionale.local'])['status'], 'forgot submit ok (generic)');
T::ok((int) $pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() >= 1, 'a reset token row was created');

$adminId = (int) $pdo->query("SELECT id FROM users WHERE email = 'admin@gestionale.local'")->fetchColumn();
$pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
    ->execute([$adminId, hash('sha256', 'KNOWN-TOKEN-123')]);
T::ok(str_contains((string) $anon->get('/reset-password?token=KNOWN-TOKEN-123', ['json' => false])['body'], 'new_password'), 'valid token shows the reset form');
T::ok(str_contains((string) $anon->get('/reset-password?token=bogus', ['json' => false])['body'], 'non è valido'), 'invalid token is rejected');
$rWeak = $admin->post('/reset-password', ['token' => 'KNOWN-TOKEN-123', 'new_password' => 'short', 'new_password_confirm' => 'short']);
T::ok(str_contains((string) $rWeak['body'], 'almeno'), 'reset rejects a too-short password');
T::equals(200, $admin->post('/reset-password', ['token' => 'KNOWN-TOKEN-123', 'new_password' => 'NuovaPass123', 'new_password_confirm' => 'NuovaPass123'])['status'], 'reset with a valid token succeeds');
T::equals(200, (new HttpClient($baseUrl))->login('admin@gestionale.local', 'NuovaPass123')['status'], 'login works with the reset password');
$pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash('password', PASSWORD_DEFAULT), $adminId]);

// Audit log
T::equals(200, $admin->get('/admin/audit', ['json' => false])['status'], 'audit page renders');
T::equals(403, $worker1->get('/admin/audit', ['json' => false])['status'], 'worker blocked from audit');
$admin->post('/admin/users', ['name' => 'Audit Test', 'email' => 'audit-test@x.local', 'role' => 'worker', 'password' => 'Password123']);
T::ok((int) $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action = 'created' AND entity = 'user'")->fetchColumn() >= 1, 'user creation is audited');
T::ok(str_contains((string) $admin->get('/admin/audit', ['json' => false])['body'], 'Audit Test'), 'audit entry appears on the page');

// ---------------------------------------------------------------------------
T::section('E2E: admin CRUD (client / project / warehouse + ledger)');
$r = $admin->post('/admin/clients', ['name' => 'Cliente E2E Srl', 'email' => 'e2e@cliente.it']);
T::equals(200, $r['status'], 'client created');
$newClientId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($newClientId > 0, 'client id returned');

$r = $admin->post('/admin/clients', ['name' => '', 'email' => 'x']);
T::equals(422, $r['status'], 'client validation rejects empty name');

$r = $admin->post('/admin/projects', [
    'client_id' => $newClientId, 'name' => 'Progetto E2E', 'location' => 'Test',
    'start_date' => date('Y-m-d'), 'status' => 'active',
]);
T::equals(200, $r['status'], 'project created');
$newProjectId = (int) ($r['json']['data']['id'] ?? 0);

$r = $admin->post('/admin/projects', [
    'client_id' => $newClientId, 'name' => 'Date rotte',
    'start_date' => date('Y-m-d'), 'end_date' => '2020-01-01', 'status' => 'active',
]);
T::equals(422, $r['status'], 'project end<start rejected');

$r = $admin->post('/admin/warehouse', ['name' => 'Articolo E2E', 'sku' => 'E2E-01', 'unit' => 'pcs', 'reorder_level' => '5']);
T::equals(200, $r['status'], 'warehouse item created');
$newItemId = (int) ($r['json']['data']['id'] ?? 0);
T::equals(0.0, $stockOf($newItemId), 'new item starts at zero stock');

$r = $admin->post("/admin/warehouse/{$newItemId}/movement", ['type' => 'in', 'qty' => '100', 'note' => 'carico E2E']);
T::equals(200, $r['status'], 'in movement accepted');
T::equals(100.0, $stockOf($newItemId), 'stock = 100 after load');

$r = $admin->post("/admin/warehouse/{$newItemId}/movement", ['type' => 'adjustment', 'qty' => '-10']);
T::equals(200, $r['status'], 'negative adjustment accepted');
T::equals(90.0, $stockOf($newItemId), 'stock = 90 after adjustment');

$r = $admin->post("/admin/warehouse/{$newItemId}/movement", ['type' => 'adjustment', 'qty' => '-500']);
T::equals(422, $r['status'], 'adjustment below zero blocked');
$r = $admin->post("/admin/warehouse/{$newItemId}/movement", ['type' => 'in', 'qty' => '999999999999']);
T::equals(422, $r['status'], 'overflow quantity blocked');
$r = $admin->post("/admin/warehouse/{$newItemId}/movement", ['type' => 'out', 'qty' => '1']);
T::equals(422, $r['status'], 'manual "out" movement not allowed (only in/adjustment)');

$pdo->exec("UPDATE warehouse_items SET qty_in_stock = 42 WHERE id = {$newItemId}");
$r = $admin->post("/admin/warehouse/{$newItemId}/reconcile");
T::equals(200, $r['status'], 'reconcile endpoint ok');
T::ok(($r['json']['data']['changed'] ?? false) === true, 'reconcile detected the drift');
T::equals(90.0, $stockOf($newItemId), 'reconcile restored ledger truth');

// ---------------------------------------------------------------------------
T::section('E2E: user management');
$r = $admin->post('/admin/users', ['name' => 'Operaio E2E', 'email' => 'worker3@test.local', 'role' => 'worker', 'password' => 'Password123']);
T::equals(200, $r['status'], 'worker3 created');
$worker3Id = (int) ($r['json']['data']['id'] ?? 0);

$r = $admin->post('/admin/users', ['name' => 'Dup', 'email' => 'worker3@test.local', 'role' => 'worker', 'password' => 'Password123']);
T::equals(422, $r['status'], 'duplicate email rejected');
$r = $admin->post('/admin/users', ['name' => 'NoClient', 'email' => 'nc@test.local', 'role' => 'client', 'password' => 'Password123']);
T::equals(422, $r['status'], 'client login without linked company rejected');
$r = $admin->post('/admin/users', ['name' => 'Shorty', 'email' => 'st@test.local', 'role' => 'worker', 'password' => 'abc']);
T::equals(422, $r['status'], 'short password rejected');

$worker3 = new HttpClient($baseUrl);
T::equals(200, $worker3->login('worker3@test.local', 'Password123')['status'], 'worker3 can log in');

$r = $admin->post("/admin/users/{$worker3Id}/toggle");
T::equals(200, $r['status'], 'worker3 deactivated');
$tmp = new HttpClient($baseUrl);
T::equals(401, $tmp->login('worker3@test.local', 'Password123')['status'], 'deactivated user cannot log in');
$admin->post("/admin/users/{$worker3Id}/toggle");
T::equals(200, (new HttpClient($baseUrl))->login('worker3@test.local', 'Password123')['status'], 'reactivated user can log in');

$r = $admin->post('/admin/users/1/toggle');
T::equals(422, $r['status'], 'admin cannot deactivate own account');

$r = $admin->post("/admin/users/{$worker3Id}", ['name' => 'Operaio E2E', 'email' => 'worker3@test.local', 'role' => 'worker', 'password' => 'NuovaPass456']);
T::equals(200, $r['status'], 'admin password reset ok');
T::equals(200, (new HttpClient($baseUrl))->login('worker3@test.local', 'NuovaPass456')['status'], 'login with reset password');

// ---------------------------------------------------------------------------
T::section('E2E: intervention lifecycle (reserve → work → complete)');
$stockBefore = $stockOf(1);
$r = $admin->post('/admin/interventions', [
    'project_id' => 1, 'assigned_worker_id' => $worker3Id, 'title' => 'Intervento E2E',
    'scheduled_date' => date('Y-m-d'), 'scheduled_start_time' => '09:00',
    'item_id' => [1], 'qty_planned' => ['5'],
]);
T::equals(200, $r['status'], 'intervention created with materials');
$e2eIvId = (int) ($r['json']['data']['id'] ?? 0);
T::equals($stockBefore - 5, $stockOf(1), 'stock reserved on creation');

$r = $admin->post('/admin/interventions', [
    'project_id' => 1, 'title' => 'Doppio materiale',
    'item_id' => [1, 1], 'qty_planned' => ['1', '2'],
]);
T::equals(422, $r['status'], 'duplicate material rows rejected');

$worker3->csrf = '';
$r = $worker3->get('/worker', ['json' => false]);
// re-read CSRF from an authenticated page for subsequent worker POSTs
if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $r['body'], $m)) {
    $worker3->csrf = $m[1];
}
T::ok(str_contains($r['body'], 'Intervento E2E'), 'worker sees the task in today list');

$r = $worker1->get("/worker/interventions/{$e2eIvId}", ['json' => false]);
T::equals(404, $r['status'], "another worker can't see the intervention");
$r = $worker1->post("/worker/interventions/{$e2eIvId}/status", ['to_status' => 'in_progress']);
T::equals(404, $r['status'], "another worker can't transition it");

$r = $worker3->post("/worker/interventions/{$e2eIvId}/status", ['to_status' => 'completed']);
T::equals(422, $r['status'], 'worker quick-transition to completed rejected (must use complete())');

$r = $worker3->post("/worker/interventions/{$e2eIvId}/status", ['to_status' => 'in_progress']);
T::equals(200, $r['status'], 'worker starts the intervention');

// --- Intervention checklist (migration 026): admin manage + worker toggle ----
T::section('Intervention checklist: RBAC + ownership + idempotent toggle');
$rTask = $admin->post("/admin/interventions/{$e2eIvId}/tasks", ['label' => 'Scrostare la parete']);
T::ok(($rTask['json']['ok'] ?? false) === true, 'admin adds a checklist item');
$taskId = (int) $pdo->query("SELECT id FROM intervention_tasks WHERE intervention_id = {$e2eIvId} ORDER BY id DESC LIMIT 1")->fetchColumn();
T::ok($taskId > 0, 'checklist item persisted');
T::equals(422, $admin->post("/admin/interventions/{$e2eIvId}/tasks", ['label' => ''])['status'], 'empty label rejected');

// A non-owner worker cannot toggle; the owner can.
T::equals(404, $worker1->post("/worker/interventions/{$e2eIvId}/tasks/{$taskId}/toggle", ['done' => 1])['status'], "non-owner worker can't toggle a checklist item");
T::ok(($worker3->post("/worker/interventions/{$e2eIvId}/tasks/{$taskId}/toggle", ['done' => 1])['json']['ok'] ?? false) === true, 'owner worker ticks the item');
T::equals(1, (int) $pdo->query("SELECT is_done FROM intervention_tasks WHERE id = {$taskId}")->fetchColumn(), 'item marked done');
// Absolute set → replay is idempotent (offline-safe).
$worker3->post("/worker/interventions/{$e2eIvId}/tasks/{$taskId}/toggle", ['done' => 1]);
T::equals(1, (int) $pdo->query("SELECT is_done FROM intervention_tasks WHERE id = {$taskId}")->fetchColumn(), 'repeated done stays done (idempotent)');
$worker3->post("/worker/interventions/{$e2eIvId}/tasks/{$taskId}/toggle", ['done' => 0]);
T::equals(0, (int) $pdo->query("SELECT is_done FROM intervention_tasks WHERE id = {$taskId}")->fetchColumn(), 'owner unticks the item');
// A task id under the wrong intervention is a 404 (no cross-intervention writes).
T::equals(404, $admin->post("/admin/interventions/999999/tasks/{$taskId}/toggle", ['done' => 1])['status'], 'cross-intervention toggle rejected');
// Admin deletes it.
T::ok(($admin->post("/admin/interventions/{$e2eIvId}/tasks/{$taskId}/delete")['json']['ok'] ?? false) === true, 'admin deletes the checklist item');
T::equals(0, (int) $pdo->query("SELECT COUNT(*) FROM intervention_tasks WHERE id = {$taskId}")->fetchColumn(), 'checklist item removed');

// --- Intervention work timer (migration 029): start/stop + ownership --------
T::section('Intervention timer: start/stop + ownership');
T::equals(404, $worker1->post("/worker/interventions/{$e2eIvId}/timer/start")['status'], "non-owner worker can't start a timer");
T::ok(($worker3->post("/worker/interventions/{$e2eIvId}/timer/start")['json']['ok'] ?? false) === true, 'owner starts the timer');
T::equals(1, (int) $pdo->query("SELECT COUNT(*) FROM intervention_time_entries WHERE intervention_id = {$e2eIvId} AND ended_at IS NULL")->fetchColumn(), 'one running timer');
T::ok(($worker3->post("/worker/interventions/{$e2eIvId}/timer/start")['json']['ok'] ?? false) === true, 're-start on the same job is a no-op');
T::equals(1, (int) $pdo->query("SELECT COUNT(*) FROM intervention_time_entries WHERE intervention_id = {$e2eIvId} AND ended_at IS NULL")->fetchColumn(), 'still exactly one running timer');
T::ok(($worker3->post("/worker/interventions/{$e2eIvId}/timer/stop")['json']['ok'] ?? false) === true, 'owner stops the timer');
T::equals(0, (int) $pdo->query("SELECT COUNT(*) FROM intervention_time_entries WHERE intervention_id = {$e2eIvId} AND ended_at IS NULL")->fetchColumn(), 'no running timer after stop');
T::equals(1, (int) $pdo->query("SELECT COUNT(*) FROM intervention_time_entries WHERE intervention_id = {$e2eIvId}")->fetchColumn(), 'one completed time entry recorded');

// --- Client account view (CRM) ----------------------------------------------
T::section('E2E: client account view sections');
$acctClient = (int) $pdo->query('SELECT id FROM clients ORDER BY id LIMIT 1')->fetchColumn();
$acct = $admin->get('/admin/clients/' . $acctClient, ['json' => false]);
T::equals(200, $acct['status'], 'admin opens the client account page');
T::ok(str_contains($acct['body'], 'Storico interventi'), 'client page shows the job-history section');
T::ok(str_contains($acct['body'], 'Preventivi'), 'client page shows the quotes section');

$matId = (int) $pdo->query("SELECT id FROM intervention_materials WHERE intervention_id = {$e2eIvId}")->fetchColumn();
$r = $worker3->post("/worker/interventions/{$e2eIvId}/complete", ["qty_used[{$matId}]" => '3']);
T::equals(422, $r['status'], 'completion blocked without after photo');

$png = $makePng();
$r = $worker3->request('POST', "/worker/interventions/{$e2eIvId}/photos", [
    'multipart' => [
        'photo' => new CURLFile($png, 'image/png', 'photo.png'), 'type' => 'before',
        'lat' => '43.3050000', 'lng' => '13.4530000', 'captured_at' => '1751800000000',
    ],
]);
T::equals(200, $r['status'], 'before photo uploaded');
// Geo-photo evidence (v2 Phase 5): coordinates + capture time are persisted.
$beforePhotoId = (int) ($r['json']['data']['id'] ?? 0);
$geoRow = $pdo->query("SELECT lat, lng, captured_at FROM photos WHERE id = {$beforePhotoId}")->fetch();
T::ok($geoRow['lat'] !== null && $geoRow['lng'] !== null, 'photo geotag persisted (lat/lng)');
T::ok($geoRow['captured_at'] !== null, 'photo capture time persisted');
$r = $worker3->request('POST', "/worker/interventions/{$e2eIvId}/photos", [
    'multipart' => ['photo' => new CURLFile($png, 'image/png', 'photo.png'), 'type' => 'after'],
]);
T::equals(200, $r['status'], 'after photo uploaded');
$afterPhotoId = (int) ($r['json']['data']['id'] ?? 0);
@unlink($png);

$r = $worker3->get("/worker/photos/{$afterPhotoId}/thumb", ['json' => false]);
T::equals(200, $r['status'], 'worker streams own photo thumb');
T::ok(str_contains($r['headers'], 'image/'), 'photo has image content type');

$sig = 'data:image/png;base64,' . base64_encode((string) file_get_contents($makePng()));
$r = $worker3->post("/worker/interventions/{$e2eIvId}/signature", ['signature' => $sig]);
T::equals(200, $r['status'], 'signature saved');

$r = $worker3->post("/worker/interventions/{$e2eIvId}/complete", []);
T::equals(422, $r['status'], 'completion blocked without qty_used');

$r = $worker3->post("/worker/interventions/{$e2eIvId}/complete", ["qty_used[{$matId}]" => '3', 'completion_notes' => 'Fatto E2E']);
T::equals(200, $r['status'], 'completion accepted');
T::equals($stockBefore - 3, $stockOf(1), 'net stock effect = qty_used (5 reserved, 2 released)');

$status = $pdo->query("SELECT status FROM interventions WHERE id = {$e2eIvId}")->fetchColumn();
T::equals('completed', $status, 'intervention completed');

$r = $admin->post("/admin/interventions/{$e2eIvId}/status", ['to_status' => 'cancelled']);
T::equals(422, $r['status'], 'admin cannot cancel a completed intervention');
$r = $admin->post("/admin/interventions/{$e2eIvId}/status", ['to_status' => 'bogus']);
T::equals(422, $r['status'], 'invalid target status rejected');

// ---------------------------------------------------------------------------
T::section('E2E: admin detail page + photo/signature access');
$r = $admin->get("/admin/interventions/{$e2eIvId}", ['json' => false]);
T::equals(200, $r['status'], 'admin detail page renders');
T::ok(str_contains($r['body'], 'Intervento E2E'), 'detail shows the title');
T::ok(str_contains($r['body'], 'Fatto E2E'), 'detail shows completion notes');
T::ok(str_contains($r['body'], 'Cronologia'), 'detail shows the status history');

$r = $admin->get("/admin/photos/{$afterPhotoId}/thumb", ['json' => false]);
T::equals(200, $r['status'], 'admin streams any photo');
$r = $admin->get("/admin/interventions/{$e2eIvId}/signature", ['json' => false]);
T::equals(200, $r['status'], 'admin streams the signature');
$r = $worker1->get("/worker/photos/{$afterPhotoId}/thumb", ['json' => false]);
T::equals(404, $r['status'], "another worker can't stream the photo");

// ---------------------------------------------------------------------------
T::section('E2E: client portal scoping + reports');
$r = $client1->get('/client', ['json' => false]);
T::ok(str_contains($r['body'], 'Ristrutturazione Villa Rossi'), 'client1 sees own project');
$r = $client1->get('/client/projects/1', ['json' => false]);
T::equals(200, $r['status'], 'client1 opens own project');
T::ok(str_contains($r['body'], 'Intervento E2E'), 'project page lists the intervention');

$client2 = new HttpClient($baseUrl);
$client2->login('client2@gestionale.local', 'password');
T::equals(404, $client2->get('/client/projects/1', ['json' => false])['status'], "client2 can't open client1's project");
T::equals(404, $client2->get("/client/photos/{$afterPhotoId}/thumb", ['json' => false])['status'], "client2 can't stream client1's photo");
T::equals(200, $client1->get("/client/photos/{$afterPhotoId}/thumb", ['json' => false])['status'], 'client1 streams own project photo');

$r = $client1->get('/client/projects/1/report/pdf', ['json' => false]);
T::equals(200, $r['status'], 'client PDF report downloads');
T::ok(str_starts_with($r['body'], '%PDF'), 'PDF magic bytes');
$r = $client1->get('/client/projects/1/report/excel', ['json' => false]);
T::equals(200, $r['status'], 'client Excel report downloads');
T::ok(str_starts_with($r['body'], 'PK'), 'XLSX magic bytes');
T::equals(404, $client2->get('/client/projects/1/report/pdf', ['json' => false])['status'], "client2 can't download client1's report");

$r = $admin->get('/admin/projects/1/report/pdf', ['json' => false]);
T::equals(200, $r['status'], 'admin PDF report downloads');
T::ok(str_starts_with($r['body'], '%PDF'), 'admin PDF magic bytes');

// ---------------------------------------------------------------------------
T::section('E2E: password change + logout');
$r = $worker1->post('/password', ['current_password' => 'wrong', 'new_password' => 'NuovaPass789', 'new_password_confirm' => 'NuovaPass789']);
T::equals(422, $r['status'], 'wrong current password rejected');
$r = $worker1->post('/password', ['current_password' => 'password', 'new_password' => 'NuovaPass789', 'new_password_confirm' => 'different']);
T::equals(422, $r['status'], 'mismatched confirmation rejected');
$r = $worker1->post('/password', ['current_password' => 'password', 'new_password' => 'NuovaPass789', 'new_password_confirm' => 'NuovaPass789']);
T::equals(200, $r['status'], 'password changed');
T::equals(200, (new HttpClient($baseUrl))->login('worker1@gestionale.local', 'NuovaPass789')['status'], 'login with the new password');

$r = $worker1->post('/logout');
T::equals(200, $r['status'], 'logout ok');
$r = $worker1->get('/worker', ['json' => false]);
T::equals(302, $r['status'], 'session gone after logout');

// ---------------------------------------------------------------------------
T::section('E2E: worker tabs + dashboard content');
$r = $worker3->get('/worker?tab=done', ['json' => false]);
T::equals(200, $r['status'], 'done tab renders');
T::ok(str_contains($r['body'], 'Intervento E2E'), 'completed task listed in done tab');
$r = $worker3->get('/worker?tab=upcoming', ['json' => false]);
T::equals(200, $r['status'], 'upcoming tab renders');

$pdo->exec('UPDATE warehouse_items SET qty_in_stock = 1 WHERE id = 10'); // force a low-stock row (reorder 50)
$r = $admin->get('/admin', ['json' => false]);
T::ok(str_contains($r['body'], 'Guanti da lavoro'), 'dashboard shows low-stock item');
$r = $admin->post('/admin/warehouse/10/reconcile');
T::equals(200, $r['status'], 'cleanup: reconcile item 10');

// ---------------------------------------------------------------------------
T::section('E2E: public lead capture + admin inbox');
// The public request form is reachable without authentication.
$reqPage = $anon->get('/request', ['json' => false]);
T::equals(200, $reqPage['status'], 'public /request form served to anonymous');
if (preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $reqPage['body'], $mLead)) {
    $anon->csrf = $mLead[1];  // token the public form carries, for the POST
}

$leadsBefore = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
// Honeypot filled → silently accepted, nothing stored.
$anon->post('/request', ['name' => 'Bot', 'email' => 'bot@x.com', 'website' => 'http://spam']);
T::equals($leadsBefore, (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn(), 'honeypot submission stores no lead');
// No contact method → 422.
T::equals(422, $anon->post('/request', ['name' => 'Mario Rossi'])['status'], 'lead without email/phone rejected');
// Valid submission stores a lead.
T::equals(200, $anon->post('/request', ['name' => 'Mario Rossi', 'phone' => '3331234567', 'message' => 'Rifacimento facciata'])['status'], 'valid public request accepted');
$leadId = (int) $pdo->query("SELECT id FROM leads WHERE name = 'Mario Rossi' ORDER BY id DESC LIMIT 1")->fetchColumn();
T::ok($leadId > 0, 'lead stored');
T::equals('new', (string) $pdo->query("SELECT status FROM leads WHERE id = {$leadId}")->fetchColumn(), 'new lead starts in status new');

// Inbox is admin-only.
T::equals(403, $worker3->get('/admin/leads')['status'], 'worker cannot open the lead inbox');
T::equals(200, $admin->get('/admin/leads', ['json' => false])['status'], 'admin opens the lead inbox');
T::ok(($admin->post("/admin/leads/{$leadId}/status", ['status' => 'contacted'])['json']['ok'] ?? false) === true, 'admin marks the lead contacted');
T::equals('contacted', (string) $pdo->query("SELECT status FROM leads WHERE id = {$leadId}")->fetchColumn(), 'status persisted');
T::equals(422, $admin->post("/admin/leads/{$leadId}/status", ['status' => 'bogus'])['status'], 'invalid status rejected');

// Convert to a client.
T::ok(($admin->post("/admin/leads/{$leadId}/convert")['json']['ok'] ?? false) === true, 'admin converts the lead to a client');
$leadClientId = (int) $pdo->query("SELECT client_id FROM leads WHERE id = {$leadId}")->fetchColumn();
T::ok($leadClientId > 0, 'lead linked to the created client');
T::equals('Mario Rossi', (string) $pdo->query("SELECT name FROM clients WHERE id = {$leadClientId}")->fetchColumn(), 'client created from the lead');
T::equals('converted', (string) $pdo->query("SELECT status FROM leads WHERE id = {$leadId}")->fetchColumn(), 'lead marked converted');
T::equals(422, $admin->post("/admin/leads/{$leadId}/convert")['status'], 'an already-converted lead cannot reconvert');
// Clean up the admin/global notification this created so later scheduler assertions stay exact.
$pdo->exec("DELETE FROM notifications WHERE dedup_key = 'lead:{$leadId}'");

// ---------------------------------------------------------------------------
T::section('E2E: output hygiene');
foreach ([['/login', $anon], ['/admin', $admin], ['/worker', $worker3], ['/client', $client1]] as [$path, $client]) {
    $r = $client->get($path, ['json' => false]);
    $clean = !preg_match('/(Warning:|Notice:|Deprecated:|Fatal error)/', $r['body']);
    T::ok($clean, "no PHP warnings on {$path}");
}
