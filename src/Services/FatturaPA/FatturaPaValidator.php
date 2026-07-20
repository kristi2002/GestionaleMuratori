<?php
declare(strict_types=1);

namespace App\Services\FatturaPA;

use App\Support\Lang;

/**
 * Pre-flight check that a fiscal invoice carries everything a valid FatturaPA needs,
 * before the builder emits it. Returns human-readable Italian problems (empty = ready);
 * the controller shows them so the user fixes the data rather than sending a file the
 * SdI would bounce.
 */
final class FatturaPaValidator
{
    /**
     * @param array<string,mixed> $company
     * @param array<string,mixed> $client
     * @param array<string,mixed> $invoice
     * @param array<int,array<string,mixed>> $lines
     * @return array<int,string> problems (empty when the invoice is ready to export)
     */
    public static function validate(array $company, array $client, array $invoice, array $lines): array
    {
        $errors = [];

        // Seller (CedentePrestatore).
        $sellerOk = trim((string) ($company['denominazione'] ?? '')) !== ''
            && (trim((string) ($company['partita_iva'] ?? '')) !== '' || trim((string) ($company['codice_fiscale'] ?? '')) !== '')
            && trim((string) ($company['regime_fiscale'] ?? '')) !== ''
            && trim((string) ($company['indirizzo'] ?? '')) !== ''
            && trim((string) ($company['cap'] ?? '')) !== ''
            && trim((string) ($company['comune'] ?? '')) !== ''
            && trim((string) ($company['provincia'] ?? '')) !== '';
        if (!$sellerOk) {
            $errors[] = Lang::get('admin.invoices.xml_err_company');
        }

        // Buyer (CessionarioCommittente): identifier.
        $kind = (string) ($client['client_kind'] ?? 'business');
        $hasPiva = trim((string) ($client['partita_iva'] ?? '')) !== '';
        $hasCf   = trim((string) ($client['codice_fiscale'] ?? '')) !== '';
        if ($kind === 'private') {
            if (!$hasCf && !$hasPiva) {
                $errors[] = Lang::get('admin.invoices.xml_err_client_cf');
            }
        } elseif (!$hasPiva && !$hasCf) {
            $errors[] = Lang::get('admin.invoices.xml_err_client_id');
        }

        // Buyer address.
        $addrOk = trim((string) ($client['address'] ?? '')) !== ''
            && trim((string) ($client['cap'] ?? '')) !== ''
            && trim((string) ($client['comune'] ?? '')) !== ''
            && trim((string) ($client['provincia'] ?? '')) !== '';
        if (!$addrOk) {
            $errors[] = Lang::get('admin.invoices.xml_err_client_address');
        }

        // Buyer SdI routing.
        $hasCodice = trim((string) ($client['codice_destinatario'] ?? '')) !== '';
        $hasPec    = trim((string) ($client['pec'] ?? '')) !== '';
        if ($kind === 'pa') {
            if (!$hasCodice) {
                $errors[] = Lang::get('admin.invoices.xml_err_pa_codice');
            }
        } elseif (!$hasCodice && !$hasPec) {
            $errors[] = Lang::get('admin.invoices.xml_err_client_routing');
        }

        // Document body.
        if ($lines === []) {
            $errors[] = Lang::get('admin.invoices.xml_err_no_lines');
        }
        foreach ($lines as $line) {
            if ((float) ($line['vat_rate'] ?? 0) === 0.0 && trim((string) ($line['natura'] ?? '')) === '') {
                $errors[] = Lang::get('admin.invoices.xml_err_natura');
                break;
            }
        }

        return $errors;
    }
}
