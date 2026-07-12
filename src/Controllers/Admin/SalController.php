<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\SalDocumentModel;
use App\Models\SalLineModel;
use App\Models\WarehouseItemModel;
use App\Services\Report\SalPdfBuilder;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\Storage;
use App\Support\Validate;
use App\Support\View;

/**
 * Generatore di S.A.L. (Stato Avanzamento Lavori). A numbered progress statement
 * per project with priced line items; draft → issued (locked PDF via SalPdfBuilder)
 * → signed (DL signature captured). Only a draft is editable.
 */
final class SalController
{
    /** DECIMAL(12,2) ceiling for the document/line amount columns. */
    private const MAX_AMOUNT = 9999999999.99;

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projects  = (new ProjectModel())->all();
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0 && $projects !== []) {
            $projectId = (int) $projects[0]['id'];
        }

        Response::html(View::render('admin/sal/index', [
            'title'     => Lang::get('admin.sal.title'),
            'projects'  => $projects,
            'projectId' => $projectId,
            'documents' => $projectId > 0 ? (new SalDocumentModel())->forProject($projectId) : [],
        ], 'layout'));
    }

    /** GET /admin/sal/create — blank S.A.L. form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projects  = (new ProjectModel())->all();
        $projectId = (int) $request->input('project_id', 0);
        if ($projectId <= 0 && $projects !== []) {
            $projectId = (int) $projects[0]['id'];
        }

        Response::html(View::render('admin/sal/form', [
            'title'     => Lang::get('admin.sal.new'),
            'sal'       => null,
            'projects'  => $projects,
            'projectId' => $projectId,
        ], 'layout'));
    }

    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = (new SalDocumentModel())->find((int) $id);
        if ($doc === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/sal/show', [
            'title'    => Lang::get('admin.sal.title') . ' n. ' . $doc['number'],
            'document' => $doc,
            'lines'    => (new SalLineModel())->forDocument((int) $id),
            'items'    => (new WarehouseItemModel())->all(''),
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $projectId = (int) $request->input('project_id', 0);
        if ((new ProjectModel())->find($projectId) === null) {
            Response::fail(Lang::get('admin.sal.project_invalid'), 422);
            return;
        }

        $model = new SalDocumentModel();
        $id = $model->create([
            'project_id'  => $projectId,
            'number'      => $model->nextNumber($projectId),
            'period_from' => $this->nullableDate($request->input('period_from', '')),
            'period_to'   => $this->nullableDate($request->input('period_to', '')),
            'description' => $this->nullable($request->input('description', '')),
            'created_by'  => Auth::id(),
        ]);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = $this->draftOrFail((int) $id);
        if ($doc === null) {
            return;
        }

        (new SalDocumentModel())->updateHeader((int) $id, [
            'period_from' => $this->nullableDate($request->input('period_from', '')),
            'period_to'   => $this->nullableDate($request->input('period_to', '')),
            'description' => $this->nullable($request->input('description', '')),
        ]);
        Response::ok();
    }

    /** POST /admin/sal/{id}/lines — add a priced line (draft only). */
    public function addLine(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = $this->draftOrFail((int) $id);
        if ($doc === null) {
            return;
        }

        // Optional warehouse item: prefills description / unit / unit_price from unit_cost.
        $item = null;
        $itemId = (int) $request->input('item_id', 0);
        if ($itemId > 0) {
            $item = (new WarehouseItemModel())->find($itemId);
        }

        $description = $this->nullable($request->input('description', ''))
            ?? ($item !== null ? (string) $item['name'] : null);
        if ($description === null || $description === '') {
            Response::fail(Lang::get('admin.sal.line_description_required'), 422);
            return;
        }

        $qty = trim((string) $request->input('qty', ''));
        if (!Validate::isQty($qty) || (float) $qty <= 0) {
            Response::fail(Lang::get('admin.sal.line_qty_invalid'), 422);
            return;
        }

        $unitPrice = trim((string) $request->input('unit_price', ''));
        if ($unitPrice === '' && $item !== null && $item['unit_cost'] !== null) {
            $unitPrice = (string) $item['unit_cost'];
        }
        if ($unitPrice === '' || !is_numeric($unitPrice) || (float) $unitPrice < 0 || (float) $unitPrice > 99999999.9999) {
            Response::fail(Lang::get('admin.sal.line_price_invalid'), 422);
            return;
        }

        $amount = round((float) $qty * (float) $unitPrice, 2);
        if ($amount > self::MAX_AMOUNT) {
            Response::fail(Lang::get('admin.sal.line_amount_overflow'), 422);
            return;
        }

        $unit = $this->nullable($request->input('unit', ''))
            ?? ($item !== null ? (string) $item['unit'] : null);

        (new SalLineModel())->create([
            'sal_id'      => (int) $id,
            'description' => $description,
            'qty'         => $qty,
            'unit'        => $unit,
            'unit_price'  => $unitPrice,
            'amount'      => number_format($amount, 2, '.', ''),
        ]);
        $total = (new SalDocumentModel())->recomputeAmount((int) $id);
        Response::ok(['amount' => $total]);
    }

    /** POST /admin/sal/{id}/lines/{lineId}/delete — remove a line (draft only). */
    public function deleteLine(Request $request, string $id, string $lineId): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = $this->draftOrFail((int) $id);
        if ($doc === null) {
            return;
        }

        $lineModel = new SalLineModel();
        $line = $lineModel->find((int) $lineId);
        if ($line === null || (int) $line['sal_id'] !== (int) $id) {
            Response::fail(Lang::get('admin.sal.line_not_found'), 404);
            return;
        }

        $lineModel->delete((int) $lineId);
        $total = (new SalDocumentModel())->recomputeAmount((int) $id);
        Response::ok(['amount' => $total]);
    }

    /** POST /admin/sal/{id}/issue — freeze a draft and generate the locked PDF. */
    public function issue(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = $this->draftOrFail((int) $id);
        if ($doc === null) {
            return;
        }

        $lines = (new SalLineModel())->forDocument((int) $id);
        if ($lines === []) {
            Response::fail(Lang::get('admin.sal.issue_no_lines'), 422);
            return;
        }

        $pdf = (new SalPdfBuilder())->build(['document' => $doc, 'lines' => $lines, 'signatureSrc' => null]);
        $relPath = 'sal/' . $doc['project_id'] . '/sal-' . $doc['number'] . '.pdf';
        (Storage::disk())->put($relPath, $pdf);

        (new SalDocumentModel())->markIssued((int) $id, $relPath);
        Response::ok();
    }

    /** POST /admin/sal/{id}/sign — capture the DL signature (issued → signed). */
    public function sign(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = (new SalDocumentModel())->find((int) $id);
        if ($doc === null) {
            Response::fail(Lang::get('admin.sal.not_found'), 404);
            return;
        }
        if ($doc['status'] !== 'issued') {
            Response::fail(Lang::get('admin.sal.not_issued'), 422);
            return;
        }

        $dataUrl = (string) $request->input('signature', '');
        $prefix  = 'data:image/png;base64,';
        if (!str_starts_with($dataUrl, $prefix)) {
            Response::fail(Lang::get('admin.sal.signature_empty'), 422);
            return;
        }
        // Cap the payload before decoding: a signature PNG is a few KB, so anything
        // this large is abuse. Mirrors the worker signature/photo size guards.
        if (strlen($dataUrl) > 5_000_000) {
            Response::fail(Lang::get('admin.sal.signature_too_large'), 422);
            return;
        }
        $binary = base64_decode(substr($dataUrl, strlen($prefix)), true);
        if ($binary === false || $binary === '') {
            Response::fail(Lang::get('admin.sal.signature_empty'), 422);
            return;
        }

        $relPath = 'sal/' . $doc['project_id'] . '/sal-' . $doc['number'] . '-sign.png';
        (Storage::disk())->put($relPath, $binary);

        (new SalDocumentModel())->markSigned((int) $id, $relPath);
        Response::ok();
    }

    /** GET /admin/sal/{id}/pdf — regenerate the S.A.L. PDF (embeds the DL signature when signed). */
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $doc = (new SalDocumentModel())->find((int) $id);
        if ($doc === null || $doc['status'] === 'draft') {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $storage = Storage::disk();
        $signatureSrc = null;
        if ($doc['status'] === 'signed' && $doc['signature_path'] !== null && $storage->exists($doc['signature_path'])) {
            $signatureSrc = 'file:///' . str_replace('\\', '/', $storage->absolutePath($doc['signature_path']));
        }

        $pdf = (new SalPdfBuilder())->build([
            'document'     => $doc,
            'lines'        => (new SalLineModel())->forDocument((int) $id),
            'signatureSrc' => $signatureSrc,
        ]);
        $filename = 'SAL-' . $doc['number'] . '-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $doc['project_name']) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /** Load the document and ensure it is still a draft; sends a fail response otherwise. */
    private function draftOrFail(int $id): ?array
    {
        $doc = (new SalDocumentModel())->find($id);
        if ($doc === null) {
            Response::fail(Lang::get('admin.sal.not_found'), 404);
            return null;
        }
        if ($doc['status'] !== 'draft') {
            Response::fail(Lang::get('admin.sal.not_draft'), 422);
            return null;
        }
        return $doc;
    }

    private function nullable(mixed $value): ?string
    {
        $v = trim((string) $value);
        return $v !== '' ? $v : null;
    }

    private function nullableDate(mixed $value): ?string
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $v);
        return ($d !== false && $d->format('Y-m-d') === $v) ? $v : null;
    }
}
