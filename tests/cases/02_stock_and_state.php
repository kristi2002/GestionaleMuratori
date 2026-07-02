<?php
/**
 * Service-level tests for the hard business logic (§4): ledger math on the
 * reserve→commit flow, the status state machine, the completion gate, and
 * cache reconciliation. Runs directly against InterventionService on the
 * seeded test database.
 */
declare(strict_types=1);

use App\Models\WarehouseItemModel;
use App\Services\InterventionService;
use App\Support\Database;

/** @var PDO $pdo (from run.php) */

$svc   = new InterventionService();
$items = new WarehouseItemModel();

$stockOf = static function (int $itemId) use ($pdo): float {
    $stmt = $pdo->prepare('SELECT qty_in_stock FROM warehouse_items WHERE id = ?');
    $stmt->execute([$itemId]);
    return (float) $stmt->fetchColumn();
};
$ledgerSum = static function (int $itemId) use ($pdo): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE
            WHEN type IN ('in','release') THEN qty
            WHEN type = 'reserve' THEN -qty
            WHEN type = 'adjustment' THEN qty
            ELSE 0 END), 0)
         FROM stock_movements WHERE item_id = ?"
    );
    $stmt->execute([$itemId]);
    return (float) $stmt->fetchColumn();
};
$movementCount = static function (int $interventionId, string $type) use ($pdo): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM stock_movements WHERE intervention_id = ? AND type = ?');
    $stmt->execute([$interventionId, $type]);
    return (int) $stmt->fetchColumn();
};
$statusOf = static function (int $id) use ($pdo): string {
    $stmt = $pdo->prepare('SELECT status FROM interventions WHERE id = ?');
    $stmt->execute([$id]);
    return (string) $stmt->fetchColumn();
};
$addAfterPhoto = static function (int $interventionId) use ($pdo): void {
    $stmt = $pdo->prepare(
        "INSERT INTO photos (intervention_id, project_id, type, file_path, thumb_path, uploaded_by)
         SELECT id, project_id, 'after', 'test/after.jpg', NULL, 1 FROM interventions WHERE id = ?"
    );
    $stmt->execute([$interventionId]);
};

// Seed facts: item 1 = Cemento (200 in stock), item 2 = Sabbia (5000 in stock); worker1 = user id 2.
$ADMIN = 1;
$WORKER = 2;

T::section('Service: reservation on create (§4.2)');
$before1 = $stockOf(1);
$before2 = $stockOf(2);
$ivId = $svc->create(
    [
        'project_id' => 1, 'assigned_worker_id' => $WORKER, 'title' => 'Test riserva',
        'description' => null, 'scheduled_date' => date('Y-m-d'), 'scheduled_start_time' => null,
    ],
    [
        ['item_id' => 2, 'qty_planned' => '300'],
        ['item_id' => 1, 'qty_planned' => '20'],
    ],
    $ADMIN
);
T::ok($ivId > 0, 'intervention created');
T::equals($before1 - 20, $stockOf(1), 'item 1 stock decremented by reserve');
T::equals($before2 - 300, $stockOf(2), 'item 2 stock decremented by reserve');
T::equals(2, $movementCount($ivId, 'reserve'), 'two reserve movements written');
T::equals($ledgerSum(1), $stockOf(1), 'cache matches ledger (item 1)');
T::equals('pending', $statusOf($ivId), 'starts pending');

T::section('Service: insufficient stock blocks creation');
T::throws(
    static fn () => $svc->create(
        ['project_id' => 1, 'assigned_worker_id' => null, 'title' => 'Troppa sabbia',
         'description' => null, 'scheduled_date' => null, 'scheduled_start_time' => null],
        [['item_id' => 2, 'qty_planned' => '999999']],
        $ADMIN
    ),
    'creation rejected when stock is insufficient'
);
T::equals($before2 - 300, $stockOf(2), 'failed creation leaves stock untouched (transaction rolled back)');
$orphan = $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = 'Troppa sabbia'")->fetchColumn();
T::equals(0, (int) $orphan, 'failed creation leaves no orphan intervention row');

T::section('Service: state machine (§4.3)');
T::throws(static fn () => $svc->transition($ivId, 'completed', $ADMIN), 'pending→completed rejected (transition() never completes)');
T::throws(static fn () => $svc->transition($ivId, 'on_hold', $ADMIN), 'pending→on_hold rejected');
$svc->transition($ivId, 'in_progress', $WORKER);
T::equals('in_progress', $statusOf($ivId), 'pending→in_progress allowed');
$startedAt = $pdo->query("SELECT started_at FROM interventions WHERE id = {$ivId}")->fetchColumn();
T::ok($startedAt !== null, 'started_at set on first in_progress');
$svc->transition($ivId, 'on_hold', $WORKER);
$svc->transition($ivId, 'in_progress', $WORKER);
T::equals($startedAt, $pdo->query("SELECT started_at FROM interventions WHERE id = {$ivId}")->fetchColumn(), 'started_at written only once');
$histCount = $pdo->query("SELECT COUNT(*) FROM intervention_status_history WHERE intervention_id = {$ivId}")->fetchColumn();
T::equals(4, (int) $histCount, 'every step recorded in history (create + 3 transitions)');

T::section('Service: completion gate (§4.4)');
$mats = $pdo->query("SELECT id, item_id FROM intervention_materials WHERE intervention_id = {$ivId} ORDER BY item_id")->fetchAll();
$qtyUsed = [];
foreach ($mats as $m) {
    $qtyUsed[(int) $m['id']] = $m['item_id'] == 1 ? '15' : '300';
}
T::throws(static fn () => $svc->complete($ivId, $WORKER, $qtyUsed, null), 'completion rejected without an after photo');
$addAfterPhoto($ivId);
T::throws(static fn () => $svc->complete($ivId, $WORKER, [], null), 'completion rejected without qty_used');
$svc->complete($ivId, $WORKER, $qtyUsed, 'Note finali');
T::equals('completed', $statusOf($ivId), 'completed via complete()');
T::equals($before1 - 15, $stockOf(1), 'item 1 net consumption = qty_used (reserve corrected by release)');
T::equals($before2 - 300, $stockOf(2), 'item 2 fully consumed (no release needed)');
T::equals(2, $movementCount($ivId, 'out'), 'an out movement per consumed material');
T::equals(1, $movementCount($ivId, 'release'), 'surplus release only for the under-used material');
T::equals($ledgerSum(1), $stockOf(1), 'cache matches ledger after completion (item 1)');
T::equals($ledgerSum(2), $stockOf(2), 'cache matches ledger after completion (item 2)');
T::throws(static fn () => $svc->transition($ivId, 'cancelled', $ADMIN), 'completed is terminal');

T::section('Service: cancellation releases reservations');
$before1 = $stockOf(1);
$iv2 = $svc->create(
    ['project_id' => 1, 'assigned_worker_id' => $WORKER, 'title' => 'Da annullare',
     'description' => null, 'scheduled_date' => null, 'scheduled_start_time' => null],
    [['item_id' => 1, 'qty_planned' => '10']],
    $ADMIN
);
T::equals($before1 - 10, $stockOf(1), 'reservation decremented stock');
$svc->transition($iv2, 'cancelled', $ADMIN);
T::equals($before1, $stockOf(1), 'cancellation restored stock');
T::equals(1, $movementCount($iv2, 'release'), 'release movement written on cancel');
$reserved = $pdo->query("SELECT COUNT(*) FROM intervention_materials WHERE intervention_id = {$iv2} AND is_reserved = 1")->fetchColumn();
T::equals(0, (int) $reserved, 'materials no longer flagged reserved');
T::throws(static fn () => $svc->transition($iv2, 'in_progress', $ADMIN), 'cancelled is terminal');

T::section('Service: ledger reconciliation (§4.1)');
$pdo->exec('UPDATE warehouse_items SET qty_in_stock = 123456 WHERE id = 1');
$recomputed = (new WarehouseItemModel())->recomputeStock(1);
T::equals($ledgerSum(1), (float) $recomputed, 'recompute restores the ledger truth');
T::equals($ledgerSum(1), $stockOf(1), 'cache fixed after drift');
