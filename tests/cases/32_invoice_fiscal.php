<?php
/**
 * Fiscal invoice data layer: the InvoiceTotals calculator (imponibile / imposta /
 * DatiRiepilogo buckets / ritenuta / bollo) and ProjectInvoiceModel persisting
 * line items + cached totals, while legacy amount-only invoices still work.
 */
declare(strict_types=1);

use App\Models\ProjectInvoiceModel;
use App\Support\InvoiceTotals;

/** @var PDO $pdo */

T::section('InvoiceTotals: VAT math and riepilogo grouping');

$t1 = InvoiceTotals::compute([
    ['qty' => 1, 'unit_price' => 1000, 'vat_rate' => 22],
    ['qty' => 2, 'unit_price' => 100,  'vat_rate' => 10],
]);
T::equals(1200.0, $t1['imponibile'], 'imponibile sums line totals');
T::equals(240.0, $t1['imposta'], 'imposta = 22% of 1000 + 10% of 200');
T::equals(1440.0, $t1['total_document'], 'total document = imponibile + imposta');
T::equals(2, count($t1['riepilogo']), 'two VAT rates → two riepilogo buckets');

// Reverse charge (inversione contabile): 0% with a natura, its own bucket, no VAT.
$t2 = InvoiceTotals::compute([
    ['qty' => 1, 'unit_price' => 1000, 'vat_rate' => 22],
    ['qty' => 1, 'unit_price' => 500,  'vat_rate' => 0, 'natura' => 'N6.3'],
]);
T::equals(1500.0, $t2['imponibile'], 'reverse-charge line adds to imponibile');
T::equals(220.0, $t2['imposta'], 'reverse-charge line carries no VAT');
T::equals(2, count($t2['riepilogo']), 'reverse-charge line is its own bucket');

// Ritenuta d'acconto + bollo.
$t3 = InvoiceTotals::compute(
    [['qty' => 1, 'unit_price' => 1000, 'vat_rate' => 22]],
    ['ritenuta_rate' => 20, 'bollo' => 2]
);
T::equals(200.0, $t3['ritenuta'], 'ritenuta = 20% of imponibile');
T::equals(1222.0, $t3['total_document'], 'total document includes bollo');
T::equals(1022.0, $t3['net_to_pay'], 'net to pay = total document − ritenuta');

T::section('ProjectInvoiceModel: fiscal invoice with lines');

$projectId = (int) $pdo->query('SELECT id FROM projects ORDER BY id LIMIT 1')->fetchColumn();
$adminId   = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();

$im  = new ProjectInvoiceModel();
$iid = $im->create([
    'project_id'    => $projectId,
    'number'        => '2026/FISCAL-1',
    'issue_date'    => '2026-04-01',
    'amount'        => '0',
    'status'        => 'draft',
    'note'          => null,
    'document_type' => 'TD01',
    'ritenuta_rate' => '20',
    'bollo'         => '0',
    'created_by'    => $adminId,
], [
    ['description' => 'Opere murarie', 'qty' => '1', 'unit' => 'corpo', 'unit_price' => '1000', 'vat_rate' => '22', 'natura' => null],
    ['description' => 'Subappalto',    'qty' => '1', 'unit' => 'corpo', 'unit_price' => '500',  'vat_rate' => '0',  'natura' => 'N6.3'],
]);

$inv = $im->find($iid);
T::equals('1500.00', (string) $inv['imponibile'], 'cached imponibile');
T::equals('220.00', (string) $inv['imposta'], 'cached imposta (reverse-charge line excluded)');
T::equals('1720.00', (string) $inv['amount'], 'amount cached = total document');
T::equals('300.00', (string) $inv['ritenuta_amount'], 'ritenuta cached = 20% of 1500');

$lines = $im->lines($iid);
T::equals(2, count($lines), 'both lines stored');
T::equals('N6.3', (string) $lines[1]['natura'], 'reverse-charge natura stored');
T::equals('1000.00', (string) $lines[0]['line_total'], 'line total computed and stored');

T::section('ProjectInvoiceModel: legacy amount-only invoice still works');

$legacyId = $im->create([
    'project_id' => $projectId,
    'number'     => '2026/LEGACY-1',
    'issue_date' => '2026-04-02',
    'amount'     => '750.00',
    'status'     => 'issued',
    'note'       => null,
    'created_by' => $adminId,
]);
$legacy = $im->find($legacyId);
T::equals('750.00', (string) $legacy['amount'], 'legacy amount preserved');
T::ok($legacy['imponibile'] === null, 'legacy invoice has no fiscal breakdown');
T::equals(0, count($im->lines($legacyId)), 'legacy invoice has no lines');
