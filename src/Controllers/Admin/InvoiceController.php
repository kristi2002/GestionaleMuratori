<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectModel;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\Report\InvoicePdfBuilder;
use App\Services\Report\ReportFilename;
use App\Support\Auth;
use App\Support\Fiscal;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validate;
use App\Support\View;

/** Sidebar "Fatture": all invoices across projects, with a printable receipt PDF. */
final class InvoiceController
{
    private const STATUSES = ['draft', 'issued', 'paid'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'search'     => trim((string) $request->input('q', '')),
            'status'     => (string) $request->input('status', ''),
            'project_id' => (int) $request->input('project_id', 0),
        ];

        $model     = new ProjectInvoiceModel();
        $paginator = Paginator::fromRequest($request, $model->count($filters), 25);

        // Real per-status counts drive the pill badges; summary() feeds the KPI cards.
        $statusCounts = $model->statusCounts();

        Response::html(View::render('admin/invoices/index', [
            'title'        => Lang::get('admin.invoices.title'),
            'invoices'     => $model->all($filters, $paginator->perPage, $paginator->offset),
            'projects'     => (new ProjectModel())->all(),
            'filters'      => $filters,
            'statuses'     => self::STATUSES,
            'statusCounts' => $statusCounts,
            'totalCount'   => array_sum($statusCounts),
            'summary'      => $model->summary(),
            'overdueDays'  => $model->overdueDays(),
            'paginator'    => $paginator,
        ], 'layout'));
    }

    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/invoices/form', [
            'title'           => Lang::get('admin.invoices.new'),
            'invoice'         => null,
            'lines'           => [],
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => (new ProjectInvoiceModel())->nextNumberSuggestion(),
        ] + $this->fiscalOptions(), 'layout'));
    }

    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $invoice = (new ProjectInvoiceModel())->find((int) $id);
        if ($invoice === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/invoices/form', [
            'title'           => Lang::get('admin.invoices.edit'),
            'invoice'         => $invoice,
            'lines'           => (new ProjectInvoiceModel())->lines((int) $id),
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => '',
        ] + $this->fiscalOptions(), 'layout'));
    }

    /** Dropdown option lists shared by the create/edit form. */
    private function fiscalOptions(): array
    {
        return [
            'docTypes'       => Fiscal::DOC_TYPES,
            'nature'         => Fiscal::NATURE,
            'paymentMethods' => Fiscal::PAYMENT_METHODS,
            'ritenutaTipi'   => Fiscal::RITENUTA_TIPI,
        ];
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $validated = $this->validated($request);
        if ($validated === null) {
            return;
        }
        [$data, $lines] = $validated;
        $data['created_by'] = Auth::id();

        $id = (new ProjectInvoiceModel())->create($data, $lines);
        if (($data['status'] ?? '') === 'issued') {
            $this->notifyInvoiceIssued($id);
        }
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model    = new ProjectInvoiceModel();
        $existing = $model->find((int) $id);
        if ($existing === null) {
            Response::fail(Lang::get('admin.invoices.not_found'), 404);
            return;
        }

        $validated = $this->validated($request);
        if ($validated === null) {
            return;
        }
        [$data, $lines] = $validated;

        $model->update((int) $id, $data, $lines);
        // Notify only on the transition into "issued", not on later edits/paid.
        if (($existing['status'] ?? '') !== 'issued' && ($data['status'] ?? '') === 'issued') {
            $this->notifyInvoiceIssued((int) $id);
        }
        Response::ok();
    }

    /**
     * Best-effort client alert when an invoice is issued: an e-mail plus an in-app
     * notification in the client portal's bell. Never breaks the request.
     */
    private function notifyInvoiceIssued(int $invoiceId): void
    {
        try {
            $invoice = (new ProjectInvoiceModel())->findWithDetails($invoiceId);
            if ($invoice === null) {
                return;
            }
            MailService::invoiceIssued($invoice);
            NotificationService::notifyClient((int) ($invoice['client_id'] ?? 0), [
                'type'      => 'system',
                'severity'  => 'info',
                'title'     => sprintf(Lang::get('notifications.client_invoice_issued'), (string) $invoice['number']),
                'body'      => Lang::get('notifications.client_invoice_issued_body'),
                'link'      => '/client',
                'dedup_key' => 'client_invoice_issued:' . $invoiceId,
            ]);
        } catch (\Throwable $e) {
            \App\Support\Logger::exception($e, ['context' => 'notifyInvoiceIssued', 'invoice_id' => $invoiceId]);
        }
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new ProjectInvoiceModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.invoices.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /** GET /admin/invoices/{id}/print — printable receipt ("ricevuta") PDF. */
    public function print(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model   = new ProjectInvoiceModel();
        $invoice = $model->findWithDetails((int) $id);
        if ($invoice === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $pdf      = (new InvoicePdfBuilder())->build([
            'invoice'      => $invoice,
            'lines'        => $model->lines((int) $id),
            'generated_at' => date('d/m/Y H:i'),
        ]);
        $filename = ReportFilename::make((string) $invoice['number'], 'pdf', 'fattura');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}|null
     *   [invoice fields, fiscal line items] — lines empty for a legacy amount-only
     *   invoice. Null when a fail response was already sent.
     */
    private function validated(Request $request): ?array
    {
        $projectId = (int) $request->input('project_id', 0);
        $project   = $projectId > 0 ? (new ProjectModel())->find($projectId) : null;
        if ($project === null) {
            Response::fail(Lang::get('admin.invoices.project_invalid'), 422);
            return null;
        }

        $number = trim((string) $request->input('number', ''));
        if ($number === '') {
            Response::fail(Lang::get('admin.projects.invoice_number_required'), 422);
            return null;
        }

        $issueDate = trim((string) $request->input('issue_date', ''));
        if (!Validate::isDate($issueDate)) {
            Response::fail(Lang::get('admin.projects.invoice_date_invalid'), 422);
            return null;
        }

        $status = (string) $request->input('status', 'issued');
        if (!in_array($status, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.projects.invoice_status_invalid'), 422);
            return null;
        }

        $lines = $this->parseLines($request);
        if ($lines === null) {
            return null; // a validation error was already sent
        }

        // Legacy path: no line items → the admin enters a single amount directly.
        if ($lines === []) {
            $amountRaw = str_replace(',', '.', trim((string) $request->input('amount', '')));
            if ($amountRaw === '' || !Validate::isMoney($amountRaw) || (float) $amountRaw <= 0) {
                Response::fail(Lang::get('admin.projects.invoice_amount_invalid'), 422);
                return null;
            }
            $amount = number_format((float) $amountRaw, 2, '.', '');
        } else {
            $amount = '0'; // recomputed from lines by the model
        }

        $note = trim((string) $request->input('note', ''));

        // CIG/CUP: use the invoice's own value if given, else inherit the project's.
        $cig = strtoupper(str_replace(' ', '', (string) $request->input('cig', ''))) ?: (string) ($project['cig'] ?? '');
        $cup = strtoupper(str_replace(' ', '', (string) $request->input('cup', ''))) ?: (string) ($project['cup'] ?? '');

        $data = [
            'project_id' => $projectId,
            'number'     => mb_substr($number, 0, 100),
            'cig'        => $cig !== '' ? mb_substr($cig, 0, 15) : null,
            'cup'        => $cup !== '' ? mb_substr($cup, 0, 15) : null,
            'issue_date' => $issueDate,
            'amount'     => $amount,
            'status'     => $status,
            'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];

        // Fiscal document fields apply only when the invoice carries line items.
        if ($lines !== []) {
            $data += $this->fiscalFields($request);
        }

        return [$data, $lines];
    }

    /**
     * @return array<int,array<string,mixed>>|null Parsed/validated line items
     *   (empty array = none supplied); null when a validation error was sent.
     */
    private function parseLines(Request $request): ?array
    {
        $raw = $request->input('lines', []);
        if (!is_array($raw)) {
            return [];
        }

        $lines = [];
        foreach ($raw as $r) {
            if (!is_array($r)) {
                continue;
            }
            $description = trim((string) ($r['description'] ?? ''));
            $qtyRaw      = str_replace(',', '.', trim((string) ($r['qty'] ?? '')));
            $priceRaw    = str_replace(',', '.', trim((string) ($r['unit_price'] ?? '')));
            $vatRaw      = str_replace(',', '.', trim((string) ($r['vat_rate'] ?? '')));
            $unit        = trim((string) ($r['unit'] ?? ''));
            $natura      = strtoupper(trim((string) ($r['natura'] ?? '')));

            if ($description === '' && $qtyRaw === '' && $priceRaw === '') {
                continue; // blank editor row
            }
            if ($description === '') {
                Response::fail(Lang::get('admin.quotes.line_description_required'), 422);
                return null;
            }
            if ($qtyRaw === '') {
                $qtyRaw = '1';
            }
            if (!Validate::isQty($qtyRaw) || (float) $qtyRaw <= 0) {
                Response::fail(Lang::get('admin.quotes.line_qty_invalid'), 422);
                return null;
            }
            if ($priceRaw === '' || !Validate::isMoney($priceRaw)) {
                Response::fail(Lang::get('admin.quotes.line_price_invalid'), 422);
                return null;
            }
            if ($vatRaw === '' || !is_numeric($vatRaw) || (float) $vatRaw < 0 || (float) $vatRaw > 100) {
                Response::fail(Lang::get('admin.invoices.line_vat_invalid'), 422);
                return null;
            }
            // A zero-VAT line must state why (Natura); a VAT line must not carry one.
            if ((float) $vatRaw === 0.0) {
                if (!in_array($natura, Fiscal::NATURE, true)) {
                    Response::fail(Lang::get('admin.invoices.natura_required'), 422);
                    return null;
                }
            } else {
                $natura = '';
            }

            $lines[] = [
                'description' => mb_substr($description, 0, 255),
                'qty'         => $qtyRaw,
                'unit'        => $unit !== '' ? mb_substr($unit, 0, 20) : null,
                'unit_price'  => number_format((float) $priceRaw, 4, '.', ''),
                'vat_rate'    => number_format((float) $vatRaw, 2, '.', ''),
                'natura'      => $natura !== '' ? $natura : null,
            ];
        }
        return $lines;
    }

    /** Document-level fiscal fields (only used on a line-item invoice). */
    private function fiscalFields(Request $request): array
    {
        $docType = (string) $request->input('document_type', 'TD01');
        if (!in_array($docType, Fiscal::DOC_TYPES, true)) {
            $docType = 'TD01';
        }
        $ritRate = str_replace(',', '.', trim((string) $request->input('ritenuta_rate', '')));
        $ritRate = is_numeric($ritRate) && (float) $ritRate > 0 ? number_format((float) $ritRate, 2, '.', '') : null;
        $ritTipo = (string) $request->input('ritenuta_tipo', '');
        $bollo   = str_replace(',', '.', trim((string) $request->input('bollo', '')));
        $pay     = (string) $request->input('payment_method', 'MP05');
        $due     = trim((string) $request->input('payment_due', ''));

        return [
            'document_type'    => $docType,
            'ritenuta_rate'    => $ritRate,
            'ritenuta_tipo'    => $ritRate !== null && in_array($ritTipo, Fiscal::RITENUTA_TIPI, true) ? $ritTipo : null,
            'ritenuta_causale' => $ritRate !== null ? mb_substr(trim((string) $request->input('ritenuta_causale', '')), 0, 2) : null,
            'bollo'            => is_numeric($bollo) && (float) $bollo > 0 ? number_format((float) $bollo, 2, '.', '') : null,
            'split_payment'    => $request->input('split_payment', '') === '1' ? 1 : 0,
            'payment_method'   => in_array($pay, Fiscal::PAYMENT_METHODS, true) ? $pay : 'MP05',
            'payment_iban'     => $this->nullableInput($request, 'payment_iban'),
            'payment_due'      => Validate::isDate($due) ? $due : null,
        ];
    }

    private function nullableInput(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));
        return $value !== '' ? $value : null;
    }
}
