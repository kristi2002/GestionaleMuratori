<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\StockBalanceModel;
use App\Models\StockLocationModel;
use App\Models\StockMovementModel;
use App\Models\WarehouseItemModel;
use App\Support\Config;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Validate;
use RuntimeException;

/**
 * Moves stock between two locations (typically warehouse -> cantiere or back).
 * A transfer is a paired ledger write — transfer_out at the source, transfer_in at
 * the destination — of equal quantity, so the total across all locations is
 * conserved. The stock_movements ledger stays the source of truth; the per-location
 * balances and qty_in_stock are recomputed from it after the pair is written.
 */
final class StockTransferService
{
    private WarehouseItemModel $items;
    private StockLocationModel $locations;
    private StockBalanceModel $balances;
    private StockMovementModel $movements;

    public function __construct()
    {
        $this->items     = new WarehouseItemModel();
        $this->locations = new StockLocationModel();
        $this->balances  = new StockBalanceModel();
        $this->movements = new StockMovementModel();
    }

    /**
     * @throws RuntimeException on invalid input, an inactive item/location, or
     *         insufficient stock at the source (unless negative stock is allowed).
     */
    public function transfer(int $itemId, int $fromLoc, int $toLoc, string $qty, int $userId, ?string $note): void
    {
        if ($fromLoc === $toLoc) {
            throw new RuntimeException(Lang::get('admin.warehouse.transfer.same_location'));
        }
        if (!Validate::isQty($qty) || (float) $qty <= 0) {
            throw new RuntimeException(Lang::get('admin.warehouse.transfer.qty_invalid'));
        }

        $allowNegative = (bool) Config::get('inventory.allow_negative_stock', false);
        $pdo           = Database::pdo();

        $pdo->beginTransaction();
        try {
            // Lock the item row: serialises every movement for this item across all
            // locations, so the source-balance check below cannot race a concurrent move.
            $item = $this->items->findForUpdate($itemId);
            if ($item === null || (int) $item['is_active'] !== 1) {
                throw new RuntimeException(Lang::get('admin.warehouse.transfer.item_invalid'));
            }

            $from = $this->locations->find($fromLoc);
            $to   = $this->locations->find($toLoc);
            if ($from === null || $to === null
                || (int) $from['is_active'] !== 1 || (int) $to['is_active'] !== 1) {
                throw new RuntimeException(Lang::get('admin.warehouse.transfer.location_invalid'));
            }

            $available = (float) $this->balances->qty($itemId, $fromLoc);
            if (!$allowNegative && $available - (float) $qty < 0) {
                throw new RuntimeException(Lang::get('admin.warehouse.transfer.insufficient_stock'));
            }

            $this->movements->create([
                'item_id'     => $itemId,
                'location_id' => $fromLoc,
                'type'        => 'transfer_out',
                'qty'         => $qty,
                'user_id'     => $userId,
                'note'        => $note,
            ]);
            $this->movements->create([
                'item_id'     => $itemId,
                'location_id' => $toLoc,
                'type'        => 'transfer_in',
                'qty'         => $qty,
                'user_id'     => $userId,
                'note'        => $note,
            ]);

            $this->items->refreshCaches($itemId, $fromLoc);
            $this->items->refreshCaches($itemId, $toLoc);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
