<?php
/**
 * Fiscal foundation: identifier validation, company-settings round-trip, and
 * client fiscal-field persistence (the substrate for FatturaPA export).
 */
declare(strict_types=1);

use App\Models\ClientModel;
use App\Models\CompanySettingsModel;
use App\Support\Fiscal;

/** @var PDO $pdo */

T::section('Fiscal: identifier validation');

T::ok(Fiscal::isPartitaIva('00743110157'), 'valid Partita IVA accepted');
T::ok(!Fiscal::isPartitaIva('00743110158'), 'wrong check digit rejected');
T::ok(!Fiscal::isPartitaIva('123'), 'too-short P.IVA rejected');
T::ok(Fiscal::isCodiceFiscale('00743110157'), 'numeric (company) C.F. accepted');
T::ok(!Fiscal::isCodiceFiscale('NOTACODE'), 'malformed C.F. rejected');
T::ok(Fiscal::isCodiceDestinatario('0000000'), 'fallback SdI code accepted');
T::ok(Fiscal::isCodiceDestinatario('ABC123'), '6-char SdI code accepted');
T::ok(!Fiscal::isCodiceDestinatario('AB'), 'short SdI code rejected');
T::ok(in_array('RF19', Fiscal::REGIMI, true), 'forfettario regime present');

T::section('Fiscal: company settings round-trip');

$cs = new CompanySettingsModel();
$cs->save([
    'denominazione'  => 'Edilizia Test S.r.l.',
    'partita_iva'    => '00743110157',
    'regime_fiscale' => 'RF01',
    'indirizzo'      => 'Via Roma',
    'numero_civico'  => '10',
    'cap'            => '20100',
    'comune'         => 'Milano',
    'provincia'      => 'MI',
    'nazione'        => 'IT',
]);
$saved = $cs->get();
T::equals('Edilizia Test S.r.l.', (string) $saved['denominazione'], 'company denominazione persisted');
T::equals('00743110157', (string) $saved['partita_iva'], 'company P.IVA persisted');
T::ok($cs->isComplete(), 'company profile reported complete after fill');

T::section('Fiscal: client fiscal fields persist');

$cm = new ClientModel();
$cid = $cm->create([
    'name'                => 'Cliente Fiscale',
    'client_kind'         => 'business',
    'vat_or_tax_id'       => '00743110157',
    'partita_iva'         => '00743110157',
    'codice_fiscale'      => null,
    'codice_destinatario' => 'ABC123',
    'pec'                 => 'cliente@pec.it',
    'email'               => null,
    'phone'               => null,
    'address'             => 'Via Verdi 1',
    'cap'                 => '00100',
    'comune'              => 'Roma',
    'provincia'           => 'RM',
    'nazione'             => 'IT',
    'notes'               => null,
]);
$c = $cm->find($cid);
T::ok($c !== null, 'client created with fiscal fields');
T::equals('ABC123', (string) $c['codice_destinatario'], 'client codice destinatario persisted');
T::equals('RM', (string) $c['provincia'], 'client provincia persisted');
T::equals('business', (string) $c['client_kind'], 'client kind persisted');
