<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseOrderModel;
use App\Models\StockMovementModel;
use App\Models\WarehouseItemModel;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Validate;
use RuntimeException;

/**
 * Receives a delivery (DDT) against a purchase order: writes one type='in' stock
 * movement per received line, tagged with the line id, into the order's delivery
 * location. The stock_movements ledger stays the source of truth — "received"
 * quantities are always summed back from it, never cached — and the item caches
 * (qty_in_stock + per-location balance) are refreshed from the ledger after each write.
 *
 * Mirrors StockTransferService: one transaction, items locked FOR UPDATE in ascending
 * id order (deadlock avoidance), balances recomputed from the ledger before commit.
 * Receiving is repeatable, so partial deliveries accumulate; over-receiving is allowed
 * and reported as a warning rather than blocked.
 */
final class PurchaseOrderReceiptService
{
    private PurchaseOrderModel $orders;
    private WarehouseItemModel $items;
    private StockMovementModel $movements;

    public function __construct()
    {
        $this->orders    = new PurchaseOrderModel();
        $this->items     = new WarehouseItemModel();
        $this->movements = new StockMovementModel();
    }

    /**
     * @param array<int,string> $received Map of purchase_order_line_id => quantity to
     *        receive now (raw user input). Lines absent or with a non-positive/blank
     *        quantity are skipped.
     * @return array{received:int,over:array<int,string>} How many lines were received
     *         and the descriptions of any line that is now over-delivered (a warning).
     * @throws RuntimeException on an invalid/cancelled order, an invalid quantity, or a
     *         quantity aimed at a non-stock line (no warehouse item to receive into).
     */
    public function receive(int $orderId, array $received, int $userId): array
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            throw new RuntimeException(Lang::get('admin.purchase_orders.not_found'));
        }
        if ($order['status'] === 'cancelled') {
            throw new RuntimeException(Lang::get('admin.purchase_orders.receive_cancelled'));
        }

        // Index the order's stock lines (item_id NOT NULL) by line id, and validate the
        // requested quantities up front so nothing is written on a bad input.
        $stockLines = [];
        foreach ($this->orders->lines($orderId) as $line) {
            if ($line['item_id'] !== null) {
                $stockLines[(int) $line['id']] = $line;
            }
        }

        $toWrite = []; // [lineId => ['item_id'=>int, 'qty'=>string, 'description'=>string]]
        foreach ($received as $lineId => $rawQty) {
            $lineId = (int) $lineId;
            $qty    = str_replace(',', '.', trim((string) $rawQty));
            if ($qty === '' || (float) $qty <= 0) {
                continue;
            }
            if (!isset($stockLines[$lineId])) {
                throw new RuntimeException(Lang::get('admin.purchase_orders.receive_line_invalid'));
            }
            if (!Validate::isQty($qty)) {
                throw new RuntimeException(Lang::get('admin.purchase_orders.receive_qty_invalid'));
            }
            $toWrite[$lineId] = [
                'item_id'     => (int) $stockLines[$lineId]['item_id'],
                'qty'         => $qty,
                'description' => (string) $stockLines[$lineId]['description'],
            ];
        }

        if ($toWrite === []) {
            throw new RuntimeException(Lang::get('admin.purchase_orders.receive_nothing'));
        }

        $locationId = (int) $order['location_id'];
        $note       = mb_substr(Lang::get('admin.purchase_orders.receive_note') . ' ' . (string) $order['number'], 0, 255);

        // Lock every distinct item this delivery touches, in ascending id order, before
        // any write — the project-wide deadlock-avoidance rule (see CLAUDE.md).
        $itemIds = array_values(array_unique(array_map(static fn ($r): int => $r['item_id'], $toWrite)));
        sort($itemIds);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($itemIds as $itemId) {
                $item = $this->items->findForUpdate($itemId);
                if ($item === null || (int) $item['is_active'] !== 1) {
                    throw new RuntimeException(Lang::get('admin.purchase_orders.receive_item_invalid'));
                }
            }

            foreach ($toWrite as $lineId => $row) {
                $this->movements->create([
                    'item_id'                => $row['item_id'],
                    'location_id'            => $locationId,
                    'type'                   => 'in',
                    'qty'                    => $row['qty'],
                    'purchase_order_line_id' => $lineId,
                    'user_id'                => $userId,
                    'note'                   => $note,
                ]);
            }

            foreach ($itemIds as $itemId) {
                $this->items->refreshCaches($itemId, $locationId);
            }

            // Recompute the header status from the ledger (now including this delivery).
            $over = $this->refreshStatus($orderId, $stockLines);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return ['received' => count($toWrite), 'over' => $over];
    }

    /**
     * Derive the order status from received-vs-ordered quantities and persist it:
     * every stock line fully delivered -> received; some delivered -> partially_received;
     * none yet -> unchanged. Never overrides a manual 'cancelled'. Returns the
     * descriptions of any line delivered beyond its ordered quantity (over-receipt).
     *
     * @param array<int,array<string,mixed>> $stockLines Ordered stock lines by id.
     * @return array<int,string>
     */
    private function refreshStatus(int $orderId, array $stockLines): array
    {
        $received = [];
        foreach ($this->orders->linesWithReceived($orderId) as $line) {
            $received[(int) $line['id']] = (float) $line['qty_received'];
        }

        $allFull = true;
        $anyRcv  = false;
        $over    = [];
        foreach ($stockLines as $lineId => $line) {
            $ordered = (float) $line['qty'];
            $got     = $received[$lineId] ?? 0.0;
            if ($got > 0) {
                $anyRcv = true;
            }
            if ($got + 1e-9 < $ordered) {
                $allFull = false;
            }
            if ($got > $ordered + 1e-9) {
                $over[] = (string) $line['description'];
            }
        }

        if ($allFull) {
            $this->orders->setStatus($orderId, 'received');
        } elseif ($anyRcv) {
            $this->orders->setStatus($orderId, 'partially_received');
        }

        return $over;
    }
}
