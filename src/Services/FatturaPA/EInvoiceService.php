<?php
declare(strict_types=1);

namespace App\Services\FatturaPA;

use App\Models\ClientModel;
use App\Models\CompanySettingsModel;
use App\Models\EInvoiceModel;
use App\Models\ProjectInvoiceModel;
use App\Support\Storage\Storage;

/**
 * Orchestrates a fiscal invoice's electronic-invoice preparation: validate the
 * data, build the FatturaPA XML, store it durably (conservazione/audit record),
 * optionally CAdES-sign it, and record the lifecycle status.
 *
 * Transmission to the SdI is provider-specific: the default 'manual' mode leaves
 * the (signed) file ready for the admin to upload through their provider / the
 * Agenzia delle Entrate portal. An API/PEC transmitter plugs in here later.
 */
final class EInvoiceService
{
    /**
     * @return array{ok:bool,errors?:array<int,string>,record?:?array<string,mixed>,not_found?:bool}
     */
    public function prepare(int $invoiceId): array
    {
        $invoices = new ProjectInvoiceModel();
        $invoice  = $invoices->findWithDetails($invoiceId);
        if ($invoice === null) {
            return ['ok' => false, 'not_found' => true, 'errors' => []];
        }

        $company = (new CompanySettingsModel())->get();
        $client  = (new ClientModel())->find((int) $invoice['client_id']) ?? [];
        $lines   = $invoices->lines($invoiceId);

        $errors = FatturaPaValidator::validate($company, $client, $invoice, $lines);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $xml      = (new FatturaPaBuilder())->build($company, $client, $invoice, $lines);
        $filename = FatturaPaBuilder::filename($company, $invoiceId);
        $storage  = Storage::disk();
        $xmlRel   = 'einvoice/' . $filename;
        $storage->put($xmlRel, $xml);

        $isPa = ($client['client_kind'] ?? 'business') === 'pa';
        $data = [
            'format'      => $isPa ? 'FPA12' : 'FPR12',
            'progressivo' => FatturaPaBuilder::progressivo($invoiceId),
            'xml_path'    => $xmlRel,
            'signed_path' => null,
            'status'      => 'generated',
            'message'     => null,
        ];

        // Sign only when a certificate is configured; otherwise the plain XML is
        // the deliverable (many providers sign on their side).
        $signer = new CadesSigner();
        if ($signer->isConfigured()) {
            try {
                $signedRel = 'einvoice/' . $filename . '.p7m';
                $storage->put($signedRel, $signer->sign($xml));
                $data['signed_path'] = $signedRel;
                $data['status']      = 'signed';
            } catch (\Throwable $e) {
                $data['status']  = 'error';
                $data['message'] = $e->getMessage();
            }
        }

        $model = new EInvoiceModel();
        $model->upsert($invoiceId, $data);

        return ['ok' => true, 'record' => $model->forInvoice($invoiceId)];
    }
}
