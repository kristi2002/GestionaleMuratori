<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\InterventionMaterialModel;
use App\Models\InterventionModel;
use App\Models\PhotoModel;
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
 * Intervention creation with material reservation (§4.1, §4.2 create side), the
 * status state machine (§4.3 — pending/in_progress/on_hold/cancelled), and
 * completion (§4.2 commit side + §4.4 gate). 'completed' is reachable only
 * through complete(), never through transition(), since it requires the
 * qty_used/after-photo evidence transition() doesn't collect.
 */
final class InterventionService
{
    private const ALLOWED_TRANSITIONS = [
        'pending'     => ['in_progress', 'cancelled'],
        'in_progress' => ['on_hold', 'cancelled'],
        'on_hold'     => ['in_progress', 'cancelled'],
    ];

    private InterventionModel $interventions;
    private InterventionMaterialModel $materials;
    private WarehouseItemModel $items;
    private StockMovementModel $movements;
    private StockBalanceModel $balances;
    private PhotoModel $photos;

    public function __construct()
    {
        $this->interventions = new InterventionModel();
        $this->materials      = new InterventionMaterialModel();
        $this->items           = new WarehouseItemModel();
        $this->movements       = new StockMovementModel();
        $this->balances        = new StockBalanceModel();
        $this->photos           = new PhotoModel();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,array{item_id:int,qty_planned:string}> $plannedMaterials
     * @param int $locationId Location the materials are reserved from (default: main warehouse).
     * @throws RuntimeException on insufficient stock or a missing/inactive item
     */
    public function create(
        array $data,
        array $plannedMaterials,
        int $userId,
        int $locationId = StockLocationModel::MAIN_WAREHOUSE_ID
    ): int {
        $allowNegative = (bool) Config::get('inventory.allow_negative_stock', false);
        $pdo           = Database::pdo();

        // Deterministic lock order avoids deadlocks against concurrent reservations/cancellations.
        usort($plannedMaterials, static fn (array $a, array $b): int => $a['item_id'] <=> $b['item_id']);

        $pdo->beginTransaction();
        try {
            $interventionId = $this->interventions->create($data);
            $this->writeHistory($interventionId, null, 'pending', $userId);

            foreach ($plannedMaterials as $material) {
                $item = $this->items->findForUpdate($material['item_id']);
                if ($item === null || (int) $item['is_active'] !== 1) {
                    throw new RuntimeException(Lang::get('admin.interventions.material_item_invalid'));
                }

                // Availability at the reservation location: qty_in_stock is the main
                // warehouse cache; any other location reads its per-location balance.
                $available = $locationId === StockLocationModel::MAIN_WAREHOUSE_ID
                    ? (float) $item['qty_in_stock']
                    : (float) $this->balances->qty((int) $material['item_id'], $locationId);
                $remaining = $available - (float) $material['qty_planned'];
                if (!$allowNegative && $remaining < 0) {
                    throw new RuntimeException(
                        sprintf(Lang::get('admin.interventions.insufficient_stock'), $item['name'])
                    );
                }

                $this->materials->create([
                    'intervention_id' => $interventionId,
                    'item_id'         => $material['item_id'],
                    'qty_planned'     => $material['qty_planned'],
                    'is_reserved'     => true,
                ]);
                $this->movements->create([
                    'item_id'         => $material['item_id'],
                    'location_id'     => $locationId,
                    'type'            => 'reserve',
                    'qty'             => $material['qty_planned'],
                    'intervention_id' => $interventionId,
                    'user_id'         => $userId,
                    'note'            => null,
                ]);
                $this->items->refreshCaches((int) $material['item_id'], $locationId);
            }

            $pdo->commit();
            return $interventionId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @throws RuntimeException on a missing intervention or an illegal transition
     */
    public function transition(int $interventionId, string $toStatus, int $userId): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $intervention = $this->interventions->findForUpdate($interventionId);
            if ($intervention === null) {
                throw new RuntimeException(Lang::get('admin.interventions.not_found'));
            }

            $from = (string) $intervention['status'];
            if (!in_array($toStatus, self::ALLOWED_TRANSITIONS[$from] ?? [], true)) {
                throw new RuntimeException(sprintf(
                    Lang::get('admin.interventions.transition_invalid'),
                    Lang::label('intervention_status', $from),
                    Lang::label('intervention_status', $toStatus)
                ));
            }

            if ($toStatus === 'cancelled') {
                foreach ($this->materials->reservedForUpdate($interventionId) as $material) {
                    // Reservations are held at the main warehouse (see create()); release there.
                    $this->movements->create([
                        'item_id'         => $material['item_id'],
                        'location_id'     => StockLocationModel::MAIN_WAREHOUSE_ID,
                        'type'            => 'release',
                        'qty'             => $material['qty_planned'],
                        'intervention_id' => $interventionId,
                        'user_id'         => $userId,
                        'note'            => 'Rilascio per annullamento intervento',
                    ]);
                    $this->materials->markReleased((int) $material['id']);
                    $this->items->refreshCaches((int) $material['item_id'], StockLocationModel::MAIN_WAREHOUSE_ID);
                }
            }

            $startedAt = $toStatus === 'in_progress' ? (new \DateTimeImmutable())->format('Y-m-d H:i:s') : null;
            $this->interventions->setStatus($interventionId, $toStatus, $startedAt);
            $this->writeHistory($interventionId, $from, $toStatus, $userId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * §4.2 completion commit + §4.4 gate. Only legal from 'in_progress'.
     *
     * @param array<int,string> $qtyUsedByMaterialId intervention_materials.id => qty_used
     * @throws RuntimeException on a missing intervention, an illegal source status,
     *         a missing qty_used for any material, or a missing 'after' photo
     */
    public function complete(
        int $interventionId,
        int $workerId,
        array $qtyUsedByMaterialId,
        ?string $completionNotes,
        int $locationId = StockLocationModel::MAIN_WAREHOUSE_ID
    ): void {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $intervention = $this->interventions->findForUpdate($interventionId);
            if ($intervention === null) {
                throw new RuntimeException(Lang::get('admin.interventions.not_found'));
            }

            $from = (string) $intervention['status'];
            if ($from !== 'in_progress') {
                throw new RuntimeException(sprintf(
                    Lang::get('admin.interventions.transition_invalid'),
                    Lang::label('intervention_status', $from),
                    Lang::label('intervention_status', 'completed')
                ));
            }

            $materials = $this->materials->forInterventionForUpdate($interventionId);

            // Gate (§4.4): qty_used set for every material, at least one 'after' photo.
            foreach ($materials as $material) {
                $id  = (int) $material['id'];
                $raw = $qtyUsedByMaterialId[$id] ?? null;
                if ($raw === null || $raw === '') {
                    throw new RuntimeException(Lang::get('admin.interventions.qty_used_required'));
                }
                if (!Validate::isQty((string) $raw) || (float) $raw < 0) {
                    throw new RuntimeException(Lang::get('admin.interventions.qty_used_invalid'));
                }
            }
            if (!$this->photos->hasAfterPhoto($interventionId)) {
                throw new RuntimeException(Lang::get('admin.interventions.after_photo_required'));
            }

            // Deterministic lock order avoids deadlocks against concurrent reservations/cancellations.
            $itemIds = array_unique(array_map(static fn (array $m): int => (int) $m['item_id'], $materials));
            sort($itemIds);
            foreach ($itemIds as $itemId) {
                $this->items->findForUpdate($itemId);
            }

            foreach ($materials as $material) {
                $id         = (int) $material['id'];
                $itemId     = (int) $material['item_id'];
                $qtyPlanned = (float) $material['qty_planned'];
                $qtyUsed    = (float) $qtyUsedByMaterialId[$id];

                if ($qtyUsed > 0) {
                    $this->movements->create([
                        'item_id'         => $itemId,
                        'location_id'     => $locationId,
                        'type'            => 'out',
                        'qty'             => (string) $qtyUsed,
                        'intervention_id' => $interventionId,
                        'user_id'         => $workerId,
                        'note'            => null,
                    ]);
                }

                // Release the unused remainder ONLY for materials that were actually
                // reserved. A never-reserved row has no offsetting 'reserve' movement, so
                // emitting 'release' here would add phantom stock (release adds in the
                // ledger). This mirrors the cancel path, which releases reservedForUpdate only.
                $releaseAmt = $qtyPlanned - $qtyUsed;
                if ($releaseAmt > 0 && (int) $material['is_reserved'] === 1) {
                    $this->movements->create([
                        'item_id'         => $itemId,
                        'location_id'     => $locationId,
                        'type'            => 'release',
                        'qty'             => (string) $releaseAmt,
                        'intervention_id' => $interventionId,
                        'user_id'         => $workerId,
                        'note'            => 'Rilascio eccedenza non utilizzata',
                    ]);
                }

                $this->materials->setUsed($id, (string) $qtyUsed);
            }

            foreach ($itemIds as $itemId) {
                $this->items->refreshCaches($itemId, $locationId);
            }

            $completedAt = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->interventions->markCompleted($interventionId, $completedAt, $completionNotes);
            $this->writeHistory($interventionId, $from, 'completed', $workerId);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function writeHistory(int $interventionId, ?string $from, string $to, int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO intervention_status_history (intervention_id, from_status, to_status, changed_by)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$interventionId, $from, $to, $userId]);
    }
}
