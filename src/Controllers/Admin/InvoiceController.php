<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectModel;
use App\Services\MailService;
use App\Services\Report\InvoicePdfBuilder;
use App\Services\Report\ReportFilename;
use App\Support\Auth;
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
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => (new ProjectInvoiceModel())->nextNumberSuggestion(),
        ], 'layout'));
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
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => '',
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }
        $data['created_by'] = Auth::id();

        $id = (new ProjectInvoiceModel())->create($data);
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

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        // Notify only on the transition into "issued", not on later edits/paid.
        if (($existing['status'] ?? '') !== 'issued' && ($data['status'] ?? '') === 'issued') {
            $this->notifyInvoiceIssued((int) $id);
        }
        Response::ok();
    }

    /** Best-effort client e-mail when an invoice is issued; never breaks the request. */
    private function notifyInvoiceIssued(int $invoiceId): void
    {
        try {
            $invoice = (new ProjectInvoiceModel())->findWithDetails($invoiceId);
            if ($invoice !== null) {
                MailService::invoiceIssued($invoice);
            }
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

        $invoice = (new ProjectInvoiceModel())->findWithDetails((int) $id);
        if ($invoice === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $pdf      = (new InvoicePdfBuilder())->build([
            'invoice'      => $invoice,
            'generated_at' => date('d/m/Y H:i'),
        ]);
        $filename = ReportFilename::make((string) $invoice['number'], 'pdf', 'fattura');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0 || (new ProjectModel())->find($projectId) === null) {
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

        $amountRaw = str_replace(',', '.', trim((string) $request->input('amount', '')));
        if ($amountRaw === '') {
            Response::fail(Lang::get('admin.invoices.amount_required'), 422);
            return null;
        }
        if (!Validate::isMoney($amountRaw) || (float) $amountRaw <= 0) {
            Response::fail(Lang::get('admin.projects.invoice_amount_invalid'), 422);
            return null;
        }
        $amount = number_format((float) $amountRaw, 2, '.', '');

        $status = (string) $request->input('status', 'issued');
        if (!in_array($status, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.projects.invoice_status_invalid'), 422);
            return null;
        }

        $note = trim((string) $request->input('note', ''));

        return [
            'project_id' => $projectId,
            'number'     => mb_substr($number, 0, 100),
            'issue_date' => $issueDate,
            'amount'     => $amount,
            'status'     => $status,
            'note'       => $note !== '' ? mb_substr($note, 0, 255) : null,
        ];
    }
}
