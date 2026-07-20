<?php
/**
 * HTTP end-to-end: fiscal invoice creation through the controller — line items with
 * per-line IVA + natura, cached totals, the natura-required guard, the legacy
 * amount-only path, and the printable PDF.
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

T::section('E2E: fiscal invoice RBAC + create');

$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
T::equals(403, $worker->get('/admin/invoices/create', ['json' => false])['status'], 'worker blocked from invoice form');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');

$project = 5;
$r = $admin->post('/admin/invoices', [
    'project_id'    => $project,
    'number'        => '2026/E2E-FISCAL',
    'issue_date'    => '2026-05-01',
    'status'        => 'draft',
    'document_type' => 'TD01',
    'bollo'         => '',
    'ritenuta_rate' => '',
    'lines'         => [
        ['description' => 'Opere murarie', 'qty' => '1', 'unit' => 'corpo', 'unit_price' => '1000', 'vat_rate' => '22', 'natura' => ''],
        ['description' => 'Subappalto',    'qty' => '1', 'unit' => 'corpo', 'unit_price' => '500',  'vat_rate' => '0',  'natura' => 'N6.3'],
    ],
]);
T::ok(($r['json']['ok'] ?? false) === true, 'admin creates a fiscal invoice');
$invId = (int) ($r['json']['data']['id'] ?? 0);
T::ok($invId > 0, 'create returns an invoice id');

$row = $pdo->query("SELECT * FROM project_invoices WHERE id = {$invId}")->fetch();
T::equals('1500.00', (string) $row['imponibile'], 'imponibile computed server-side');
T::equals('220.00', (string) $row['imposta'], 'imposta excludes the reverse-charge line');
T::equals('1720.00', (string) $row['amount'], 'amount cached = total document');
$lineCount = (int) $pdo->query("SELECT COUNT(*) FROM invoice_lines WHERE invoice_id = {$invId}")->fetchColumn();
T::equals(2, $lineCount, 'both fiscal lines stored');

T::section('E2E: natura-required guard');

$bad = $admin->post('/admin/invoices', [
    'project_id' => $project,
    'number'     => '2026/E2E-BAD',
    'issue_date' => '2026-05-02',
    'status'     => 'draft',
    'lines'      => [
        ['description' => 'Senza natura', 'qty' => '1', 'unit' => '', 'unit_price' => '100', 'vat_rate' => '0', 'natura' => ''],
    ],
]);
T::equals(422, $bad['status'], 'zero-VAT line without natura is rejected');

T::section('E2E: legacy amount-only invoice still works');

$legacy = $admin->post('/admin/invoices', [
    'project_id' => $project,
    'number'     => '2026/E2E-LEGACY',
    'issue_date' => '2026-05-03',
    'status'     => 'issued',
    'amount'     => '900',
]);
T::ok(($legacy['json']['ok'] ?? false) === true, 'legacy amount-only invoice accepted');
$legacyId = (int) ($legacy['json']['data']['id'] ?? 0);
T::equals('900.00', (string) $pdo->query("SELECT amount FROM project_invoices WHERE id = {$legacyId}")->fetchColumn(), 'legacy amount stored');

T::section('E2E: fiscal invoice PDF');

$pdf = $admin->get('/admin/invoices/' . $invId . '/print', ['json' => false]);
T::equals(200, $pdf['status'], 'invoice PDF returns 200');
T::ok(str_starts_with((string) $pdf['body'], '%PDF'), 'invoice PDF is a real PDF');
