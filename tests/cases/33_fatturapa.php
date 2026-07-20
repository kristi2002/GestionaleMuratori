<?php
/**
 * FatturaPA v1.2 XML builder + pre-flight validator: a fiscal invoice with a
 * reverse-charge line and CIG produces well-formed XML carrying the expected
 * FatturaPA elements; the validator reports missing fiscal data.
 */
declare(strict_types=1);

use App\Models\ClientModel;
use App\Models\CompanySettingsModel;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectModel;
use App\Services\FatturaPA\FatturaPaBuilder;
use App\Services\FatturaPA\FatturaPaValidator;

/** @var PDO $pdo */

T::section('FatturaPA: build well-formed XML');

$cs = new CompanySettingsModel();
$cs->save([
    'denominazione'  => 'Edilizia Costruzioni S.r.l.',
    'partita_iva'    => '00743110157',
    'regime_fiscale' => 'RF01',
    'indirizzo'      => 'Via dei Muratori',
    'numero_civico'  => '12',
    'cap'            => '20100',
    'comune'         => 'Milano',
    'provincia'      => 'MI',
    'nazione'        => 'IT',
]);
$company = $cs->get();

$cm  = new ClientModel();
$cid = $cm->create([
    'name'                => 'Committente S.p.A.',
    'client_kind'         => 'business',
    'vat_or_tax_id'       => null,
    'partita_iva'         => '12345678903',
    'codice_fiscale'      => null,
    'codice_destinatario' => '1234567',
    'pec'                 => null,
    'email'               => null,
    'phone'               => null,
    'address'             => 'Via Cliente 5',
    'cap'                 => '00100',
    'comune'              => 'Roma',
    'provincia'           => 'RM',
    'nazione'             => 'IT',
    'notes'               => null,
]);
$client = $cm->find($cid);

$pm  = new ProjectModel();
$pid = $pm->create([
    'client_id' => $cid, 'name' => 'Cantiere XML', 'location' => null,
    'start_date' => '2026-01-01', 'end_date' => null, 'invoice_reference' => null,
    'cig' => '1234567890', 'cup' => null, 'status' => 'active',
]);
$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();

$im  = new ProjectInvoiceModel();
$iid = $im->create([
    'project_id'    => $pid,
    'number'        => '2026/XML-1',
    'issue_date'    => '2026-04-15',
    'amount'        => '0',
    'status'        => 'issued',
    'note'          => null,
    'document_type' => 'TD01',
    'cig'           => '1234567890',
    'payment_method'=> 'MP05',
    'created_by'    => $adminId,
], [
    ['description' => 'Opere murarie', 'qty' => '1', 'unit' => 'corpo', 'unit_price' => '1000', 'vat_rate' => '22', 'natura' => null],
    ['description' => 'Subappalto edile', 'qty' => '1', 'unit' => 'corpo', 'unit_price' => '500', 'vat_rate' => '0', 'natura' => 'N6.3'],
]);
$invoice = $im->find($iid);
$lines   = $im->lines($iid);

$errors = FatturaPaValidator::validate($company, $client, $invoice, $lines);
T::equals(0, count($errors), 'validator passes for a complete fiscal invoice');

$xml = (new FatturaPaBuilder())->build($company, $client, $invoice, $lines);

$dom = new DOMDocument();
T::ok($dom->loadXML($xml) !== false, 'generated XML is well-formed');
T::ok(str_contains($xml, 'p:FatturaElettronica') && str_contains($xml, 'versione="FPR12"'), 'root is p:FatturaElettronica FPR12');
T::ok(str_contains($xml, '<Denominazione>Edilizia Costruzioni S.r.l.</Denominazione>'), 'CedentePrestatore denominazione present');
T::ok(str_contains($xml, '<Denominazione>Committente S.p.A.</Denominazione>'), 'CessionarioCommittente denominazione present');
T::ok(str_contains($xml, '<RegimeFiscale>RF01</RegimeFiscale>'), 'regime fiscale present');
T::ok(str_contains($xml, '<Natura>N6.3</Natura>'), 'reverse-charge natura emitted');
T::ok(str_contains($xml, '<ImportoTotaleDocumento>1720.00</ImportoTotaleDocumento>'), 'document total emitted');
T::ok(str_contains($xml, '<CodiceCIG>1234567890</CodiceCIG>'), 'CIG emitted under DatiOrdineAcquisto');
T::ok(str_contains($xml, '<CodiceDestinatario>1234567</CodiceDestinatario>'), 'SdI recipient code emitted');
T::ok(str_contains($xml, '<ModalitaPagamento>MP05</ModalitaPagamento>'), 'payment method emitted');

// The VAT-bearing bucket must state esigibilità; the reverse-charge one must not.
T::ok(str_contains($xml, '<EsigibilitaIVA>I</EsigibilitaIVA>'), 'esigibilità on the taxed bucket');

$fname = FatturaPaBuilder::filename($company, $iid);
T::ok(str_starts_with($fname, 'IT00743110157_') && str_ends_with($fname, '.xml'), 'SdI filename convention');

T::section('FatturaPA: validator flags missing data');

$badClient = $client;
$badClient['codice_destinatario'] = null;
$badClient['pec'] = null;
$errs = FatturaPaValidator::validate($company, $badClient, $invoice, $lines);
T::ok(count($errs) >= 1, 'missing SdI routing is reported');

$noCompany = FatturaPaValidator::validate([], $client, $invoice, $lines);
T::ok(count($noCompany) >= 1, 'missing seller profile is reported');
