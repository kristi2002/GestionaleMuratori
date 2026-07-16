<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Models\PurchaseOrderModel;
use App\Models\StockLocationModel;
use App\Models\SupplierModel;
use App\Models\WarehouseItemModel;
use App\Services\PurchaseOrderReceiptService;
use App\Services\Report\PurchaseOrderPdfBuilder;
use App\Services\Report\ReportFilename;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\Validate;
use App\Support\View;
use RuntimeException;

/** Sidebar "Buoni d'Ordine": supplier purchase orders, printable and receivable into stock. */
final class PurchaseOrderController
{
    /** Every status (index pills + badges). */
    private const STATUSES = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'];
    /** Statuses an admin may set from the form; the received states are system-driven. */
    private const STATUSES_EDITABLE = ['draft', 'sent', 'confirmed', 'cancelled'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $filters = [
            'search'      => trim((string) $request->input('q', '')),
            'status'      => (string) $request->input('status', ''),
            'supplier_id' => (int) $request->input('supplier_id', 0),
        ];

        $model     = new PurchaseOrderModel();
        $paginator = Paginator::fromRequest($request, $model->count($filters), 25);
        $counts    = $model->statusCounts();

        Response::html(View::render('admin/purchase_orders/index', [
            'title'        => Lang::get('admin.purchase_orders.title'),
            'orders'       => $model->all($filters, $paginator->perPage, $paginator->offset),
            'suppliers'    => (new SupplierModel())->all(),
            'filters'      => $filters,
            'statuses'     => self::STATUSES,
            'statusCounts' => $counts,
            'totalCount'   => array_sum($counts),
            'summary'      => $model->summary(),
            'paginator'    => $paginator,
        ], 'layout'));
    }

    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/purchase_orders/form', $this->formData(null, []), 'layout'));
    }

    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new PurchaseOrderModel();
        $order = $model->find((int) $id);
        if ($order === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }
        // Once a delivery has been booked, the order is managed from the receive screen
        // (editing lines would desync them from the ledger movements that reference them).
        if ($model->hasReceipts((int) $id)) {
            Response::redirect(Url::to('/admin/purchase-orders/' . $id . '/receive'));
            return;
        }

        Response::html(View::render('admin/purchase_orders/form', $this->formData($order, $model->lines((int) $id)), 'layout'));
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

        $id = (new PurchaseOrderModel())->create($data, $lines);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new PurchaseOrderModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.purchase_orders.not_found'), 404);
            return;
        }
        if ($model->hasReceipts((int) $id)) {
            Response::fail(Lang::get('admin.purchase_orders.locked_by_receipts'), 409);
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

        $model = new PurchaseOrderModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.purchase_orders.not_found'), 404);
            return;
        }
        if ($model->hasReceipts((int) $id)) {
            Response::fail(Lang::get('admin.purchase_orders.locked_by_receipts'), 409);
            return;
        }

        $model->delete((int) $id);
        Response::ok();
    }

    /** GET /admin/purchase-orders/{id}/receive — delivery (DDT) booking screen. */
    public function receive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new PurchaseOrderModel();
        $order = $model->find((int) $id);
        if ($order === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/purchase_orders/receive', [
            'title' => Lang::get('admin.purchase_orders.receive_title'),
            'order' => $order,
            'lines' => $model->linesWithReceived((int) $id),
        ], 'layout'));
    }

    /** POST /admin/purchase-orders/{id}/receive — book received quantities into stock. */
    public function doReceive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $received = $request->input('received', []);
        if (!is_array($received)) {
            $received = [];
        }

        try {
            $result = (new PurchaseOrderReceiptService())->receive((int) $id, $received, (int) Auth::id());
        } catch (RuntimeException $e) {
            Response::fail($e->getMessage(), 422);
            return;
        }

        $data = ['received' => $result['received'], 'redirect' => Url::to('/admin/purchase-orders')];
        if ($result['over'] !== []) {
            $data['warning'] = sprintf(Lang::get('admin.purchase_orders.receive_over_warning'), implode(', ', $result['over']));
        }
        Response::ok($data);
    }

    /** GET /admin/purchase-orders/{id}/pdf — printable order. */
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new PurchaseOrderModel();
        $order = $model->find((int) $id);
        if ($order === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $pdf = (new PurchaseOrderPdfBuilder())->build([
            'order'        => $order,
            'lines'        => $model->lines((int) $id),
            'generated_at' => date('d/m/Y H:i'),
        ]);
        $filename = ReportFilename::make((string) $order['number'], 'pdf', 'buono-ordine');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /** Shared view payload for create/edit. */
    private function formData(?array $order, array $lines): array
    {
        return [
            'title'           => $order === null ? Lang::get('admin.purchase_orders.new') : Lang::get('admin.purchase_orders.edit'),
            'order'           => $order,
            'lines'           => $lines,
            'suppliers'       => (new SupplierModel())->listActive(),
            'projects'        => (new ProjectModel())->all(),
            'locations'       => (new StockLocationModel())->all(true),
            'items'           => (new WarehouseItemModel())->all(),
            'statuses'        => self::STATUSES_EDITABLE,
            'suggestedNumber' => $order === null ? (new PurchaseOrderModel())->nextNumberSuggestion() : '',
        ];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<int,array<string,mixed>>}|null
     *         [order fields, line items], or null if a fail response was already sent.
     */
    private function validated(Request $request): ?array
    {
        $supplierId = (int) $request->input('supplier_id', 0);
        if ($supplierId <= 0 || (new SupplierModel())->find($supplierId) === null) {
            Response::fail(Lang::get('admin.purchase_orders.supplier_invalid'), 422);
            return null;
        }

        $projectId = (int) $request->input('project_id', 0);
        if ($projectId > 0 && (new ProjectModel())->find($projectId) === null) {
            Response::fail(Lang::get('admin.purchase_orders.project_invalid'), 422);
            return null;
        }

        $locationId = (int) $request->input('location_id', 0);
        if ($locationId <= 0 || (new StockLocationModel())->find($locationId) === null) {
            Response::fail(Lang::get('admin.purchase_orders.location_invalid'), 422);
            return null;
        }

        $number = trim((string) $request->input('number', ''));
        if ($number === '') {
            Response::fail(Lang::get('admin.purchase_orders.number_required'), 422);
            return null;
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Response::fail(Lang::get('admin.purchase_orders.title_required'), 422);
            return null;
        }

        $orderDate = trim((string) $request->input('order_date', ''));
        if (!Validate::isDate($orderDate)) {
            Response::fail(Lang::get('admin.purchase_orders.date_invalid'), 422);
            return null;
        }

        $expected = trim((string) $request->input('expected_date', ''));
        if ($expected !== '' && (!Validate::isDate($expected) || $expected < $orderDate)) {
            Response::fail(Lang::get('admin.purchase_orders.expected_invalid'), 422);
            return null;
        }

        $status = (string) $request->input('status', 'draft');
        if (!in_array($status, self::STATUSES_EDITABLE, true)) {
            Response::fail(Lang::get('admin.purchase_orders.status_invalid'), 422);
            return null;
        }

        $vatRaw = str_replace(',', '.', trim((string) $request->input('vat_rate', '22')));
        if (!is_numeric($vatRaw) || (float) $vatRaw < 0 || (float) $vatRaw > 100) {
            Response::fail(Lang::get('admin.purchase_orders.vat_invalid'), 422);
            return null;
        }

        $notes = trim((string) $request->input('notes', ''));

        $lines = $this->validatedLines($request);
        if ($lines === null) {
            return null;
        }

        return [[
            'supplier_id'   => $supplierId,
            'project_id'    => $projectId > 0 ? $projectId : null,
            'location_id'   => $locationId,
            'number'        => mb_substr($number, 0, 100),
            'title'         => mb_substr($title, 0, 190),
            'order_date'    => $orderDate,
            'expected_date' => $expected !== '' ? $expected : null,
            'status'        => $status,
            'vat_rate'      => number_format((float) $vatRaw, 2, '.', ''),
            'notes'         => $notes !== '' ? $notes : null,
        ], $lines];
    }

    /**
     * @return array<int,array<string,mixed>>|null Validated line items, or null on a
     *         validation failure (a fail response was already sent).
     */
    private function validatedLines(Request $request): ?array
    {
        $items    = new WarehouseItemModel();
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
            $itemId      = (int) ($raw['item_id'] ?? 0);

            // Rows left completely empty by the editor are ignored, not rejected.
            if ($description === '' && $qtyRaw === '' && $priceRaw === '' && $itemId <= 0) {
                continue;
            }

            // A line may reference a warehouse item (received into stock) or be free-text
            // (a service, delivery charge…). When an item is chosen its name is the
            // default description and its unit fills a blank unit field.
            $item = null;
            if ($itemId > 0) {
                $item = $items->find($itemId);
                if ($item === null) {
                    Response::fail(Lang::get('admin.purchase_orders.line_item_invalid'), 422);
                    return null;
                }
                if ($description === '') {
                    $description = (string) $item['name'];
                }
                if ($unit === '') {
                    $unit = (string) $item['unit'];
                }
            }

            if ($description === '') {
                Response::fail(Lang::get('admin.purchase_orders.line_description_required'), 422);
                return null;
            }
            if ($qtyRaw === '') {
                $qtyRaw = '1';
            }
            if (!Validate::isQty($qtyRaw) || (float) $qtyRaw <= 0) {
                Response::fail(Lang::get('admin.purchase_orders.line_qty_invalid'), 422);
                return null;
            }
            if ($priceRaw === '') {
                $priceRaw = '0';
            }
            if (!Validate::isMoney($priceRaw)) {
                Response::fail(Lang::get('admin.purchase_orders.line_price_invalid'), 422);
                return null;
            }

            $lines[] = [
                'item_id'    => $itemId > 0 ? $itemId : null,
                'description' => mb_substr($description, 0, 255),
                'qty'        => $qtyRaw,
                'unit'       => $unit !== '' ? mb_substr($unit, 0, 20) : null,
                'unit_price' => number_format((float) $priceRaw, 2, '.', ''),
            ];
        }

        if ($lines === []) {
            Response::fail(Lang::get('admin.purchase_orders.lines_required'), 422);
            return null;
        }

        return $lines;
    }
}
