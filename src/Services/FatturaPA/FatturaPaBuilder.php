<?php
declare(strict_types=1);

namespace App\Services\FatturaPA;

use App\Support\InvoiceTotals;
use DOMDocument;
use DOMElement;

/**
 * Builds a FatturaPA v1.2 electronic-invoice XML from a fiscal invoice.
 *
 * Structure (unqualified children under the p:-namespaced root, per the official
 * schema's elementFormDefault="unqualified"):
 *   FatturaElettronicaHeader → DatiTrasmissione, CedentePrestatore, CessionarioCommittente
 *   FatturaElettronicaBody   → DatiGenerali, DatiBeniServizi (DettaglioLinee + DatiRiepilogo), DatiPagamento
 *
 * The builder assumes its inputs are complete — call validate() first (the
 * controller does) so missing fiscal data is reported to the user, not emitted
 * as an invalid file the SdI would reject.
 */
final class FatturaPaBuilder
{
    private const NS = 'http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2';

    private DOMDocument $doc;

    /**
     * @param array<string,mixed> $company CompanySettingsModel::get()
     * @param array<string,mixed> $client  ClientModel::find()
     * @param array<string,mixed> $invoice project_invoices row
     * @param array<int,array<string,mixed>> $lines invoice_lines rows
     */
    public function build(array $company, array $client, array $invoice, array $lines): string
    {
        $this->doc = new DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $isPa   = ($client['client_kind'] ?? 'business') === 'pa';
        $root   = $this->doc->createElementNS(self::NS, 'p:FatturaElettronica');
        $root->setAttribute('versione', $isPa ? 'FPA12' : 'FPR12');
        $this->doc->appendChild($root);

        $root->appendChild($this->header($company, $client, $invoice, $isPa));
        $root->appendChild($this->body($company, $invoice, $lines));

        return (string) $this->doc->saveXML();
    }

    // --- Header ----------------------------------------------------------------

    private function header(array $company, array $client, array $invoice, bool $isPa): DOMElement
    {
        $h = $this->el('FatturaElettronicaHeader');

        // DatiTrasmissione: who transmits + where the SdI routes the file.
        $dt   = $this->child($h, 'DatiTrasmissione');
        $idT  = $this->child($dt, 'IdTrasmittente');
        $this->child($idT, 'IdPaese', strtoupper((string) ($company['nazione'] ?: 'IT')));
        $this->child($idT, 'IdCodice', (string) ($company['partita_iva'] ?: $company['codice_fiscale']));
        $this->child($dt, 'ProgressivoInvio', self::progressivo((int) $invoice['id']));
        $this->child($dt, 'FormatoTrasmissione', $isPa ? 'FPA12' : 'FPR12');

        $codice = strtoupper(trim((string) ($client['codice_destinatario'] ?? '')));
        $pec    = trim((string) ($client['pec'] ?? ''));
        if ($codice === '') {
            $codice = $isPa ? '000000' : '0000000';
        }
        $this->child($dt, 'CodiceDestinatario', $codice);
        if (!$isPa && $codice === '0000000' && $pec !== '') {
            $this->child($dt, 'PECDestinatario', $pec);
        }

        $h->appendChild($this->cedente($company));
        $h->appendChild($this->cessionario($client));
        return $h;
    }

    private function cedente(array $c): DOMElement
    {
        $node = $this->el('CedentePrestatore');
        $da   = $this->child($node, 'DatiAnagrafici');
        if (!empty($c['partita_iva'])) {
            $iva = $this->child($da, 'IdFiscaleIVA');
            $this->child($iva, 'IdPaese', strtoupper((string) ($c['nazione'] ?: 'IT')));
            $this->child($iva, 'IdCodice', (string) $c['partita_iva']);
        }
        if (!empty($c['codice_fiscale'])) {
            $this->child($da, 'CodiceFiscale', strtoupper((string) $c['codice_fiscale']));
        }
        $ana = $this->child($da, 'Anagrafica');
        $this->child($ana, 'Denominazione', (string) $c['denominazione']);
        $this->child($da, 'RegimeFiscale', (string) ($c['regime_fiscale'] ?: 'RF01'));

        $this->sede($node, $c);

        // IscrizioneREA is mandatory for capital companies; emitted only when present.
        if (!empty($c['rea_ufficio']) && !empty($c['rea_numero'])) {
            $rea = $this->child($node, 'IscrizioneREA');
            $this->child($rea, 'Ufficio', strtoupper((string) $c['rea_ufficio']));
            $this->child($rea, 'NumeroREA', (string) $c['rea_numero']);
            if (!empty($c['capitale_sociale'])) {
                $this->child($rea, 'CapitaleSociale', $this->money($c['capitale_sociale']));
            }
            if (!empty($c['socio_unico'])) {
                $this->child($rea, 'SocioUnico', (string) $c['socio_unico']);
            }
            $this->child($rea, 'StatoLiquidazione', (string) ($c['stato_liquidazione'] ?: 'LN'));
        }
        return $node;
    }

    private function cessionario(array $c): DOMElement
    {
        $node = $this->el('CessionarioCommittente');
        $da   = $this->child($node, 'DatiAnagrafici');
        if (!empty($c['partita_iva'])) {
            $iva = $this->child($da, 'IdFiscaleIVA');
            $this->child($iva, 'IdPaese', strtoupper((string) ($c['nazione'] ?: 'IT')));
            $this->child($iva, 'IdCodice', (string) $c['partita_iva']);
        }
        if (!empty($c['codice_fiscale'])) {
            $this->child($da, 'CodiceFiscale', strtoupper((string) $c['codice_fiscale']));
        }
        $ana = $this->child($da, 'Anagrafica');
        $this->child($ana, 'Denominazione', (string) $c['name']);

        $this->sede($node, [
            'indirizzo' => $c['address'] ?? '',
            'cap'       => $c['cap'] ?? '',
            'comune'    => $c['comune'] ?? '',
            'provincia' => $c['provincia'] ?? '',
            'nazione'   => $c['nazione'] ?? 'IT',
        ]);
        return $node;
    }

    private function sede(DOMElement $parent, array $a): void
    {
        $sede = $this->child($parent, 'Sede');
        $this->child($sede, 'Indirizzo', (string) ($a['indirizzo'] ?: '—'));
        if (!empty($a['numero_civico'])) {
            $this->child($sede, 'NumeroCivico', (string) $a['numero_civico']);
        }
        $this->child($sede, 'CAP', preg_replace('/\D/', '', (string) ($a['cap'] ?: '00000')) ?: '00000');
        $this->child($sede, 'Comune', (string) ($a['comune'] ?: '—'));
        if (!empty($a['provincia'])) {
            $this->child($sede, 'Provincia', strtoupper((string) $a['provincia']));
        }
        $this->child($sede, 'Nazione', strtoupper((string) ($a['nazione'] ?: 'IT')));
    }

    // --- Body ------------------------------------------------------------------

    private function body(array $company, array $invoice, array $lines): DOMElement
    {
        $totals = InvoiceTotals::compute($lines, [
            'ritenuta_rate' => $invoice['ritenuta_rate'] ?? 0,
            'bollo'         => $invoice['bollo'] ?? 0,
        ]);

        $body = $this->el('FatturaElettronicaBody');
        $dg   = $this->child($body, 'DatiGenerali');
        $dgd  = $this->child($dg, 'DatiGeneraliDocumento');
        $this->child($dgd, 'TipoDocumento', (string) ($invoice['document_type'] ?: 'TD01'));
        $this->child($dgd, 'Divisa', 'EUR');
        $this->child($dgd, 'Data', (string) $invoice['issue_date']);
        $this->child($dgd, 'Numero', (string) $invoice['number']);

        if (!empty($invoice['ritenuta_amount']) && (float) $invoice['ritenuta_amount'] > 0) {
            $dr = $this->child($dgd, 'DatiRitenuta');
            $this->child($dr, 'TipoRitenuta', (string) ($invoice['ritenuta_tipo'] ?: 'RT02'));
            $this->child($dr, 'ImportoRitenuta', $this->money($invoice['ritenuta_amount']));
            $this->child($dr, 'AliquotaRitenuta', $this->money($invoice['ritenuta_rate']));
            $this->child($dr, 'CausalePagamento', (string) ($invoice['ritenuta_causale'] ?: 'A'));
        }
        if (!empty($invoice['bollo']) && (float) $invoice['bollo'] > 0) {
            $db = $this->child($dgd, 'DatiBollo');
            $this->child($db, 'BolloVirtuale', 'SI');
            $this->child($db, 'ImportoBollo', $this->money($invoice['bollo']));
        }
        $this->child($dgd, 'ImportoTotaleDocumento', $this->money($totals['total_document']));

        // CIG/CUP (Legge 136/2010) travel under DatiOrdineAcquisto.
        if (!empty($invoice['cig']) || !empty($invoice['cup'])) {
            $doa = $this->child($dg, 'DatiOrdineAcquisto');
            $this->child($doa, 'IdDocumento', (string) $invoice['number']);
            if (!empty($invoice['cup'])) {
                $this->child($doa, 'CodiceCUP', (string) $invoice['cup']);
            }
            if (!empty($invoice['cig'])) {
                $this->child($doa, 'CodiceCIG', (string) $invoice['cig']);
            }
        }

        $dbs = $this->child($body, 'DatiBeniServizi');
        $n   = 0;
        foreach ($lines as $line) {
            $n++;
            $dl = $this->child($dbs, 'DettaglioLinee');
            $this->child($dl, 'NumeroLinea', (string) $n);
            $this->child($dl, 'Descrizione', (string) $line['description']);
            if (!empty($line['unit'])) {
                $this->child($dl, 'Quantita', $this->qty($line['qty']));
                $this->child($dl, 'UnitaMisura', (string) $line['unit']);
            }
            $this->child($dl, 'PrezzoUnitario', $this->qty($line['unit_price']));
            $this->child($dl, 'PrezzoTotale', $this->money($line['line_total']));
            $this->child($dl, 'AliquotaIVA', $this->money($line['vat_rate']));
            if (!empty($line['natura'])) {
                $this->child($dl, 'Natura', (string) $line['natura']);
            }
        }

        $split = (int) ($invoice['split_payment'] ?? 0) === 1;
        foreach ($totals['riepilogo'] as $r) {
            $dr = $this->child($dbs, 'DatiRiepilogo');
            $this->child($dr, 'AliquotaIVA', $this->money($r['vat_rate']));
            if ($r['natura'] !== null) {
                $this->child($dr, 'Natura', (string) $r['natura']);
            }
            $this->child($dr, 'ImponibileImporto', $this->money($r['imponibile']));
            $this->child($dr, 'Imposta', $this->money($r['imposta']));
            // Esigibilità applies only to VAT-bearing lines (not reverse charge).
            if ($r['natura'] === null) {
                $this->child($dr, 'EsigibilitaIVA', $split ? 'S' : 'I');
            }
        }

        // DatiPagamento — full payment in one instalment (TP02).
        $dp = $this->child($body, 'DatiPagamento');
        $this->child($dp, 'CondizioniPagamento', 'TP02');
        $det = $this->child($dp, 'DettaglioPagamento');
        $this->child($det, 'ModalitaPagamento', (string) ($invoice['payment_method'] ?: 'MP05'));
        $this->child($det, 'ImportoPagamento', $this->money($totals['net_to_pay']));
        if (!empty($invoice['payment_due'])) {
            $this->child($det, 'DataScadenzaPagamento', (string) $invoice['payment_due']);
        }
        if (!empty($invoice['payment_iban'])) {
            $this->child($det, 'IBAN', strtoupper(str_replace(' ', '', (string) $invoice['payment_iban'])));
        }

        return $body;
    }

    // --- Helpers ---------------------------------------------------------------

    private function el(string $name): DOMElement
    {
        return $this->doc->createElement($name);
    }

    private function child(DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $node = $this->doc->createElement($name);
        if ($value !== null) {
            $node->appendChild($this->doc->createTextNode($value));
        }
        $parent->appendChild($node);
        return $node;
    }

    /** Money/percentage with exactly 2 decimals (dot separator), as the schema wants. */
    private function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /** Quantity / unit price: 2–8 decimals, trailing zeros trimmed below 2 kept. */
    private function qty(mixed $v): string
    {
        $s = rtrim(rtrim(sprintf('%.8F', (float) $v), '0'), '.');
        $dot = strpos($s, '.');
        $dec = $dot === false ? 0 : strlen($s) - $dot - 1;
        return $dec < 2 ? number_format((float) $v, 2, '.', '') : $s;
    }

    /** Unique-per-file ProgressivoInvio (SdI: 1–10 alphanumerics) from the invoice id. */
    public static function progressivo(int $invoiceId): string
    {
        return strtoupper(str_pad(base_convert((string) $invoiceId, 10, 36), 5, '0', STR_PAD_LEFT));
    }

    /** SdI filename: {IdPaese}{IdCodice}_{progressivo}.xml */
    public static function filename(array $company, int $invoiceId): string
    {
        $country = strtoupper((string) ($company['nazione'] ?: 'IT'));
        $code    = (string) ($company['partita_iva'] ?: $company['codice_fiscale']);
        return $country . $code . '_' . self::progressivo($invoiceId) . '.xml';
    }
}
