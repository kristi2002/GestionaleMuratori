<?php
/**
 * HTTP end-to-end: Generatore di S.A.L. (v2 Phase 4c). Draft CRUD, item-priced
 * lines, issue → locked PDF, issued immutability, PDF download, and DL sign-off.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

// A 1x1 PNG as a canvas-style data URL for the sign-off step.
$PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

// ---------------------------------------------------------------------------
T::section('E2E: S.A.L. access + create');
$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
T::equals(403, $worker->get('/admin/sal', ['json' => false])['status'], 'worker blocked from S.A.L.');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(200, $admin->get('/admin/sal', ['json' => false])['status'], 'admin sees S.A.L. list');

$project = 5;
$r = $admin->post('/admin/sal', ['project_id' => $project, 'period_from' => '2026-06-01', 'period_to' => '2026-06-30']);
T::ok(($r['json']['ok'] ?? false) === true, 'admin creates a draft S.A.L.');
$salId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($salId > 0, 'create returns a S.A.L. id');

// ---------------------------------------------------------------------------
T::section('E2E: S.A.L. lines (manual + item-priced)');
$r = $admin->post('/admin/sal/' . $salId . '/lines', ['description' => 'Manodopera', 'qty' => '10', 'unit' => 'h', 'unit_price' => '25']);
T::ok(($r['json']['ok'] ?? false) === true, 'manual line added');
T::equals('250.00', (string) ($r['json']['data']['amount'] ?? ''), 'document total = 10 × 25');

// Item-priced line: price prefilled from the item's unit_cost (Cemento = 6.5000).
$item = (int) $pdo->query("SELECT id FROM warehouse_items WHERE unit_cost IS NOT NULL ORDER BY id LIMIT 1")->fetchColumn();
$unitCost = (float) $pdo->query("SELECT unit_cost FROM warehouse_items WHERE id = {$item}")->fetchColumn();
$r = $admin->post('/admin/sal/' . $salId . '/lines', ['item_id' => $item, 'qty' => '4']);
T::ok(($r['json']['ok'] ?? false) === true, 'item-priced line added (price from unit_cost)');
$lineRow = $pdo->query("SELECT * FROM sal_lines WHERE sal_id = {$salId} ORDER BY id DESC LIMIT 1")->fetch();
T::equals(number_format($unitCost, 4, '.', ''), (string) $lineRow['unit_price'], 'line unit_price came from the item unit_cost');

// qty <= 0 rejected.
T::equals(422, $admin->post('/admin/sal/' . $salId . '/lines', ['description' => 'x', 'qty' => '0', 'unit_price' => '1'])['status'], 'zero qty rejected');

// Delete the first line.
$firstLine = (int) $pdo->query("SELECT id FROM sal_lines WHERE sal_id = {$salId} ORDER BY id LIMIT 1")->fetchColumn();
$r = $admin->post('/admin/sal/' . $salId . '/lines/' . $firstLine . '/delete', []);
T::ok(($r['json']['ok'] ?? false) === true, 'line deleted');

// ---------------------------------------------------------------------------
T::section('E2E: issue → locked PDF → sign');
$r = $admin->post('/admin/sal/' . $salId . '/issue', []);
T::ok(($r['json']['ok'] ?? false) === true, 'draft issued');
T::equals('issued', (string) $pdo->query("SELECT status FROM sal_documents WHERE id = {$salId}")->fetchColumn(), 'status = issued');

// Issued document is frozen: no new lines, no header edits.
T::equals(422, $admin->post('/admin/sal/' . $salId . '/lines', ['description' => 'late', 'qty' => '1', 'unit_price' => '1'])['status'], 'cannot add lines after issue');
T::equals(422, $admin->post('/admin/sal/' . $salId, ['description' => 'HACK'])['status'], 'cannot edit header after issue');

// Download the PDF.
$pdf = $admin->get('/admin/sal/' . $salId . '/pdf', ['json' => false]);
T::equals(200, $pdf['status'], 'PDF downloads');
T::ok(str_starts_with($pdf['body'], '%PDF'), 'response is a real PDF (magic bytes)');

// Sign it.
T::equals(422, $admin->post('/admin/sal/' . $salId . '/sign', ['signature' => 'not-a-data-url'])['status'], 'invalid signature rejected');
$r = $admin->post('/admin/sal/' . $salId . '/sign', ['signature' => $PNG]);
T::ok(($r['json']['ok'] ?? false) === true, 'DL signs the S.A.L.');
T::equals('signed', (string) $pdo->query("SELECT status FROM sal_documents WHERE id = {$salId}")->fetchColumn(), 'status = signed');

// Signing again (already signed) rejected.
T::equals(422, $admin->post('/admin/sal/' . $salId . '/sign', ['signature' => $PNG])['status'], 're-signing rejected');

// PDF still downloads after signing.
T::ok(str_starts_with($admin->get('/admin/sal/' . $salId . '/pdf', ['json' => false])['body'], '%PDF'), 'signed PDF still downloads');

// Draft PDF is not downloadable (404).
$r2 = $admin->post('/admin/sal', ['project_id' => $project]);
$draftId = (int) ($r2['json']['data']['id'] ?? 0);
T::equals(404, $admin->get('/admin/sal/' . $draftId . '/pdf', ['json' => false])['status'], 'draft has no downloadable PDF');

// --- S.A.L. -> draft invoice (Phase 3) ---------------------------------------
T::section('E2E: generate a draft invoice from a signed S.A.L.');
$salAmount = (string) $pdo->query("SELECT amount FROM sal_documents WHERE id = {$salId}")->fetchColumn();
$salNumber = (string) $pdo->query("SELECT number FROM sal_documents WHERE id = {$salId}")->fetchColumn();

T::equals(403, $worker->post('/admin/sal/' . $salId . '/invoice')['status'], 'worker cannot generate an invoice from a S.A.L.');
T::equals(422, $admin->post('/admin/sal/' . $draftId . '/invoice')['status'], 'a draft S.A.L. cannot be invoiced');

$conv = $admin->post('/admin/sal/' . $salId . '/invoice');
T::ok(($conv['json']['ok'] ?? false) === true, 'admin converts a signed S.A.L. to an invoice');
$invId = (int) ($conv['json']['data']['id'] ?? 0);
T::ok($invId > 0, 'conversion returns an invoice id');
$inv = $pdo->query("SELECT * FROM project_invoices WHERE id = {$invId}")->fetch();
T::equals('draft', (string) $inv['status'], 'generated invoice is a draft (admin reviews before issuing)');
T::equals($salAmount, (string) $inv['amount'], 'invoice amount equals the S.A.L. amount');
T::ok(str_contains((string) $inv['note'], $salNumber), 'invoice note references the S.A.L. number');
