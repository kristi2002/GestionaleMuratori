<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\StockBalanceModel;
use App\Models\StockLocationModel;
use App\Models\StockMovementModel;
use App\Models\WarehouseItemModel;
use App\Services\StockTransferService;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Validate;
use App\Support\View;
use RuntimeException;

final class WarehouseController
{
    private const UNITS = ['pcs', 'kg', 'm', 'l', 'box'];

    /** Movement types an admin may register directly (§4.2 reserve/release/out come from intervention logic). */
    private const MANUAL_MOVEMENT_TYPES = ['in', 'adjustment'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search = trim((string) $request->input('q', ''));

        Response::html(View::render('admin/warehouse/index', [
            'title' => Lang::get('admin.warehouse.title'),
            'items' => (new WarehouseItemModel())->all($search),
            'search' => $search,
            'units' => self::UNITS,
        ], 'layout'));
    }

    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $item = (new WarehouseItemModel())->find((int) $id);
        if ($item === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/warehouse/show', [
            'title'     => $item['name'],
            'item'      => $item,
            'movements' => (new StockMovementModel())->forItem((int) $id),
            'balances'  => (new StockBalanceModel())->forItem((int) $id),
            'locations' => (new StockLocationModel())->all(true),
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request, null);
        if ($data === null) {
            return;
        }

        $id = (new WarehouseItemModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new WarehouseItemModel();
        $item  = $model->find((int) $id);
        if ($item === null) {
            Response::fail(Lang::get('admin.warehouse.not_found'), 404);
            return;
        }

        $data = $this->validated($request, (int) $id);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function toggleActive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new WarehouseItemModel();
        $item  = $model->find((int) $id);
        if ($item === null) {
            Response::fail(Lang::get('admin.warehouse.not_found'), 404);
            return;
        }

        $model->setActive((int) $id, ((int) $item['is_active']) !== 1);
        Response::ok();
    }

    /** POST /admin/warehouse/{id}/movement — register an 'in' or 'adjustment' ledger entry (§4.1). */
    public function addMovement(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $itemModel = new WarehouseItemModel();
        $item      = $itemModel->find((int) $id);
        if ($item === null) {
            Response::fail(Lang::get('admin.warehouse.not_found'), 404);
            return;
        }

        $type = (string) $request->input('type', '');
        if (!in_array($type, self::MANUAL_MOVEMENT_TYPES, true)) {
            Response::fail(Lang::get('admin.warehouse.movement_type_invalid'), 422);
            return;
        }

        $rawQty = trim((string) $request->input('qty', ''));
        if (!Validate::isQty($rawQty) || (float) $rawQty === 0.0) {
            Response::fail(Lang::get('admin.warehouse.movement_qty_invalid'), 422);
            return;
        }
        if ($type === 'in' && (float) $rawQty <= 0) {
            Response::fail(Lang::get('admin.warehouse.movement_qty_positive'), 422);
            return;
        }

        $note = trim((string) $request->input('note', ''));
        $note = $note !== '' ? $note : null;

        $allowNegative = (bool) Config::get('inventory.allow_negative_stock', false);

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $locked = $itemModel->findForUpdate((int) $id);
            if ($locked === null) {
                // Deleted between the unlocked find() above and this locked read.
                $pdo->rollBack();
                Response::fail(Lang::get('admin.warehouse.not_found'), 404);
                return;
            }
            $newStock = (float) $locked['qty_in_stock'] + (float) $rawQty;

            if (!$allowNegative && $newStock < 0) {
                $pdo->rollBack();
                Response::fail(Lang::get('admin.warehouse.movement_negative_stock'), 422);
                return;
            }
            if (abs($newStock) > Validate::MAX_DECIMAL) {
                $pdo->rollBack();
                Response::fail(Lang::get('admin.warehouse.movement_overflow'), 422);
                return;
            }

            (new StockMovementModel())->create([
                'item_id'     => (int) $id,
                'location_id' => StockLocationModel::MAIN_WAREHOUSE_ID,
                'type'        => $type,
                'qty'         => $rawQty,
                'user_id'     => Auth::id(),
                'note'        => $note,
            ]);
            // Manual movements always land in the main warehouse; refresh both caches.
            $itemModel->refreshCaches((int) $id, StockLocationModel::MAIN_WAREHOUSE_ID);
            $newTotal = (string) $itemModel->find((int) $id)['qty_in_stock'];

            $pdo->commit();
            Response::ok(['qty_in_stock' => $newTotal]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /admin/warehouse/{id}/reconcile — §4.1 reconciliation: recompute qty_in_stock
     * from the ledger and report whether the cached value had drifted.
     */
    public function reconcile(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $itemModel = new WarehouseItemModel();
        $pdo       = Database::pdo();
        try {
            $pdo->beginTransaction();

            $locked = $itemModel->findForUpdate((int) $id);
            if ($locked === null) {
                $pdo->rollBack();
                Response::fail(Lang::get('admin.warehouse.not_found'), 404);
                return;
            }

            $before  = $locked['qty_in_stock'];
            $after   = $itemModel->recomputeStock((int) $id);
            $changed = (float) $before !== (float) $after;

            $pdo->commit();
            Response::ok([
                'before'  => $before,
                'after'   => $after,
                'changed' => $changed,
                'message' => $changed
                    ? sprintf(Lang::get('admin.warehouse.reconcile_fixed'), $before, $after)
                    : Lang::get('admin.warehouse.reconcile_clean'),
            ]);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * POST /admin/warehouse/{id}/transfer — move stock from one location to another
     * (warehouse -> cantiere or back). The service runs the whole move in one locked
     * transaction and keeps every balance cache reconciled with the ledger.
     */
    public function transfer(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $itemModel = new WarehouseItemModel();
        $item      = $itemModel->find((int) $id);
        if ($item === null) {
            Response::fail(Lang::get('admin.warehouse.not_found'), 404);
            return;
        }

        $fromLoc = (int) $request->input('from_location_id', 0);
        $toLoc   = (int) $request->input('to_location_id', 0);
        $qty     = trim((string) $request->input('qty', ''));
        $note    = trim((string) $request->input('note', ''));

        try {
            (new StockTransferService())->transfer(
                (int) $id,
                $fromLoc,
                $toLoc,
                $qty,
                (int) Auth::id(),
                $note !== '' ? $note : null
            );
        } catch (RuntimeException $e) {
            Response::fail($e->getMessage(), 422);
            return;
        }

        Response::ok([
            'from_qty' => (new StockBalanceModel())->qty((int) $id, $fromLoc),
            'to_qty'   => (new StockBalanceModel())->qty((int) $id, $toLoc),
        ]);
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request, ?int $excludeId): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.warehouse.name_required'), 422);
            return null;
        }

        $unit = (string) $request->input('unit', '');
        if (!in_array($unit, self::UNITS, true)) {
            Response::fail(Lang::get('admin.warehouse.unit_invalid'), 422);
            return null;
        }

        $sku = trim((string) $request->input('sku', ''));
        $sku = $sku !== '' ? $sku : null;
        if ($sku !== null && (new WarehouseItemModel())->skuExists($sku, $excludeId)) {
            Response::fail(Lang::get('admin.warehouse.sku_taken'), 422);
            return null;
        }

        $reorderLevel = trim((string) $request->input('reorder_level', '0'));
        if (!Validate::isQty($reorderLevel) || (float) $reorderLevel < 0) {
            Response::fail(Lang::get('admin.warehouse.reorder_invalid'), 422);
            return null;
        }

        return [
            'name'          => $name,
            'sku'           => $sku,
            'unit'          => $unit,
            'reorder_level' => $reorderLevel,
        ];
    }
}
