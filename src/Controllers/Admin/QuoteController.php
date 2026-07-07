<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\ProjectInvoiceModel;
use App\Models\ProjectModel;
use App\Models\QuoteModel;
use App\Services\Report\QuotePdfBuilder;
use App\Services\Report\ReportFilename;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validate;
use App\Support\View;

/** Sidebar "Preventivi": estimates with line items, printable as PDF. */
final class QuoteController
{
    private const STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'search'    => trim((string) $request->input('q', '')),
            'status'    => (string) $request->input('status', ''),
            'client_id' => (int) $request->input('client_id', 0),
        ];

        Response::html(View::render('admin/quotes/index', [
            'title'    => Lang::get('admin.quotes.title'),
            'quotes'   => (new QuoteModel())->all($filters),
            'clients'  => (new ClientModel())->all(),
            'filters'  => $filters,
            'statuses' => self::STATUSES,
        ], 'layout'));
    }

    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/quotes/form', [
            'title'           => Lang::get('admin.quotes.new'),
            'quote'           => null,
            'lines'           => [],
            'clients'         => (new ClientModel())->all(),
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => (new QuoteModel())->nextNumberSuggestion(),
        ], 'layout'));
    }

    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new QuoteModel();
        $quote = $model->find((int) $id);
        if ($quote === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/quotes/form', [
            'title'           => Lang::get('admin.quotes.edit'),
            'quote'           => $quote,
            'lines'           => $model->lines((int) $id),
            'clients'         => (new ClientModel())->all(),
            'projects'        => (new ProjectModel())->all(),
            'statuses'        => self::STATUSES,
            'suggestedNumber' => '',
        ], 'layout'));
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

        $id = (new QuoteModel())->create($data, $lines);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new QuoteModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.quotes.not_found'), 404);
            return;
        }

        $validated = $this->validated($request);
        if ($validated === null) {
            return;
        }
        [$data, $lines] = $validated;

        $model->update((int) $id, $data, $lines);
        Response::ok();
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new QuoteModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.quotes.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /**
     * POST /admin/quotes/{id}/invoice — turn an accepted quote into a project
     * invoice: next free number, today's date, total VAT included.
     */
    public function toInvoice(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new QuoteModel();
        $quote = $model->find((int) $id);
        if ($quote === null) {
            Response::fail(Lang::get('admin.quotes.not_found'), 404);
            return;
        }
        if ($quote['status'] !== 'accepted') {
            Response::fail(Lang::get('admin.quotes.convert_requires_accepted'), 422);
            return;
        }
        if ($quote['project_id'] === null) {
            Response::fail(Lang::get('admin.quotes.convert_requires_project'), 422);
            return;
        }

        $subtotal = 0.0;
        foreach ($model->lines((int) $id) as $line) {
            $subtotal += (float) $line['qty'] * (float) $line['unit_price'];
        }
        $total = $subtotal * (1 + (float) $quote['vat_rate'] / 100);

        $invoices  = new ProjectInvoiceModel();
        $invoiceId = $invoices->create([
            'project_id' => (int) $quote['project_id'],
            'number'     => $invoices->nextNumberSuggestion(),
            'issue_date' => date('Y-m-d'),
            'amount'     => number_format($total, 2, '.', ''),
            'status'     => 'issued',
            'note'       => mb_substr('Da preventivo n. ' . $quote['number'], 0, 255),
            'created_by' => Auth::id(),
        ]);

        Response::ok(['id' => $invoiceId, 'redirect' => Url::to('/admin/invoices/' . $invoiceId . '/edit')]);
    }

    /** GET /admin/quotes/{id}/pdf — printable estimate. */
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new QuoteModel();
        $quote = $model->find((int) $id);
        if ($quote === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $pdf      = (new QuotePdfBuilder())->build([
            'quote'        => $quote,
            'lines'        => $model->lines((int) $id),
            'generated_at' => date('d/m/Y H:i'),
        ]);
        $filename = ReportFilename::make((string) $quote['number'], 'pdf', 'preventivo');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}|null
     *         [quote fields, line items], or null if a fail response was already sent.
     */
    private function validated(Request $request): ?array
    {
        $clientId = (int) $request->input('client_id', 0);
        if ($clientId <= 0 || (new ClientModel())->find($clientId) === null) {
            Response::fail(Lang::get('admin.quotes.client_invalid'), 422);
            return null;
        }

        $projectId = (int) $request->input('project_id', 0);
        if ($projectId > 0) {
            $project = (new ProjectModel())->find($projectId);
            if ($project === null || (int) $project['client_id'] !== $clientId) {
                Response::fail(Lang::get('admin.quotes.project_invalid'), 422);
                return null;
            }
        }

        $number = trim((string) $request->input('number', ''));
        if ($number === '') {
            Response::fail(Lang::get('admin.quotes.number_required'), 422);
            return null;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Response::fail(Lang::get('admin.quotes.title_required'), 422);
            return null;
        }

        $quoteDate = trim((string) $request->input('quote_date', ''));
        if (!Validate::isDate($quoteDate)) {
            Response::fail(Lang::get('admin.quotes.date_invalid'), 422);
            return null;
        }

        $validUntil = trim((string) $request->input('valid_until', ''));
        if ($validUntil !== '' && (!Validate::isDate($validUntil) || $validUntil < $quoteDate)) {
            Response::fail(Lang::get('admin.quotes.valid_until_invalid'), 422);
            return null;
        }

        $status = (string) $request->input('status', 'draft');
        if (!in_array($status, self::STATUSES, true)) {
            Response::fail(Lang::get('admin.quotes.status_invalid'), 422);
            return null;
        }

        $vatRaw = str_replace(',', '.', trim((string) $request->input('vat_rate', '22')));
        if (!is_numeric($vatRaw) || (float) $vatRaw < 0 || (float) $vatRaw > 100) {
            Response::fail(Lang::get('admin.quotes.vat_invalid'), 422);
            return null;
        }

        $notes = trim((string) $request->input('notes', ''));

        $lines    = [];
        $rawLines = $request->input('lines', []);
        foreach (is_array($rawLines) ? $rawLines : [] as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $description = trim((string) ($raw['description'] ?? ''));
            $qtyRaw      = str_replace(',', '.', trim((string) ($raw['qty'] ?? '')));
            $priceRaw    = str_replace(',', '.', trim((string) ($raw['unit_price'] ?? '')));
            $unit        = trim((string) ($raw['unit'] ?? ''));

            // Rows left completely empty by the editor are ignored, not rejected.
            if ($description === '' && $qtyRaw === '' && $priceRaw === '') {
                continue;
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
            if ($priceRaw === '') {
                $priceRaw = '0';
            }
            if (!Validate::isMoney($priceRaw)) {
                Response::fail(Lang::get('admin.quotes.line_price_invalid'), 422);
                return null;
            }

            $lines[] = [
                'description' => mb_substr($description, 0, 255),
                'qty'         => $qtyRaw,
                'unit'        => $unit !== '' ? mb_substr($unit, 0, 20) : null,
                'unit_price'  => number_format((float) $priceRaw, 2, '.', ''),
            ];
        }

        if ($lines === []) {
            Response::fail(Lang::get('admin.quotes.lines_required'), 422);
            return null;
        }

        return [[
            'client_id'   => $clientId,
            'project_id'  => $projectId > 0 ? $projectId : null,
            'number'      => mb_substr($number, 0, 100),
            'title'       => mb_substr($title, 0, 190),
            'quote_date'  => $quoteDate,
            'valid_until' => $validUntil !== '' ? $validUntil : null,
            'status'      => $status,
            'vat_rate'    => number_format((float) $vatRaw, 2, '.', ''),
            'notes'       => $notes !== '' ? $notes : null,
        ], $lines];
    }
}
