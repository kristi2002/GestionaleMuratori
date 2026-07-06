<?php
/**
 * HTTP end-to-end: Scadenzario Sicurezza (v2 Phase 4d). Admin-only CRUD, subject
 * validation, and the ≤30-day expiry widget on the dashboard.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

// ---------------------------------------------------------------------------
T::section('E2E: compliance access + dashboard widget');
$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
T::equals(403, $worker->get('/admin/compliance', ['json' => false])['status'], 'worker blocked from compliance');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(200, $admin->get('/admin/compliance', ['json' => false])['status'], 'admin sees compliance list');

// The dashboard surfaces the expiring/expired seeded documents.
$dash = $admin->get('/admin', ['json' => false]);
T::ok(str_contains($dash['body'], 'Scadenze sicurezza'), 'dashboard shows the expiry widget label');
T::ok(str_contains($dash['body'], 'Sicurezza: documenti'), 'dashboard shows the expiring-docs table');

// ---------------------------------------------------------------------------
T::section('E2E: compliance CRUD + validation');
// company subject needs no subject_id.
$r = $admin->post('/admin/compliance', ['subject_type' => 'company', 'doc_type' => 'POS', 'reference' => 'POS-HTTP', 'expiry_date' => '2027-06-30']);
T::ok(($r['json']['ok'] ?? false) === true, 'admin creates a company compliance doc');
$docId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($docId > 0, 'create returns an id');
T::ok($pdo->query("SELECT subject_id FROM compliance_documents WHERE id = {$docId}")->fetchColumn() === null, 'company doc stores NULL subject_id');

// worker subject requires a valid worker id.
$badWorker = 999999;
T::equals(422, $admin->post('/admin/compliance', ['subject_type' => 'worker', 'subject_id' => $badWorker, 'doc_type' => 'DURC'])['status'], 'invalid worker subject rejected');
$worker1 = (int) $pdo->query("SELECT id FROM users WHERE role='worker' ORDER BY id LIMIT 1")->fetchColumn();
$r = $admin->post('/admin/compliance', ['subject_type' => 'worker', 'subject_id' => $worker1, 'doc_type' => 'visita_medica', 'expiry_date' => '2027-01-01']);
T::ok(($r['json']['ok'] ?? false) === true, 'valid worker subject accepted');

// invalid enums / dates / credits.
T::equals(422, $admin->post('/admin/compliance', ['subject_type' => 'alien', 'doc_type' => 'DURC'])['status'], 'invalid subject_type rejected');
T::equals(422, $admin->post('/admin/compliance', ['subject_type' => 'company', 'doc_type' => 'NOPE'])['status'], 'invalid doc_type rejected');
T::equals(422, $admin->post('/admin/compliance', ['subject_type' => 'company', 'doc_type' => 'DURC', 'issue_date' => '2027-01-01', 'expiry_date' => '2026-01-01'])['status'], 'expiry before issue rejected');
T::equals(422, $admin->post('/admin/compliance', ['subject_type' => 'company', 'doc_type' => 'patente_crediti', 'credits' => '-5'])['status'], 'negative credits rejected');

// patente a crediti with credits.
$r = $admin->post('/admin/compliance', ['subject_type' => 'company', 'doc_type' => 'patente_crediti', 'credits' => '75']);
T::ok(($r['json']['ok'] ?? false) === true, 'patente a crediti with credits accepted');

// update + delete.
$r = $admin->post('/admin/compliance/' . $docId, ['subject_type' => 'company', 'doc_type' => 'PSC', 'reference' => 'PSC-HTTP']);
T::ok(($r['json']['ok'] ?? false) === true, 'admin updates a compliance doc');
T::equals('PSC', (string) $pdo->query("SELECT doc_type FROM compliance_documents WHERE id = {$docId}")->fetchColumn(), 'update persisted');

$r = $admin->post('/admin/compliance/' . $docId . '/delete', []);
T::ok(($r['json']['ok'] ?? false) === true, 'admin deletes a compliance doc');
T::equals(0, (int) $pdo->query("SELECT COUNT(*) FROM compliance_documents WHERE id = {$docId}")->fetchColumn(), 'doc removed');
