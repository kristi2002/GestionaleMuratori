<?php
/**
 * Multi-site inventory (v2): per-location balances, warehouse<->cantiere transfers,
 * and the fixed completion release. Uses FRESH warehouse items so it stays isolated
 * from the other cases that run against the same shared seeded database.
 */
declare(strict_types=1);

use App\Models\StockBalanceModel;
use App\Models\StockLocationModel;
use App\Models\WarehouseItemModel;
use App\Services\InterventionService;
use App\Services\StockTransferService;

/** @var PDO $pdo (from run.php) */

$items = new WarehouseItemModel();
$bal   = new StockBalanceModel();
$loc   = new StockLocationModel();
$xfer  = new StockTransferService();
$svc   = new InterventionService();

$ADMIN     = 1;
$WORKER    = 2;
$WAREHOUSE = StockLocationModel::MAIN_WAREHOUSE_ID; // 1

$balOf   = static fn (int $i, int $l): float => (float) $bal->qty($i, $l);
$stockOf = static function (int $i) use ($pdo): float {
    $st = $pdo->prepare('SELECT qty_in_stock FROM warehouse_items WHERE id = ?');
    $st->execute([$i]);
    return (float) $st->fetchColumn();
};
// Full ledger across ALL locations (transfer-aware): transfers net to zero, so this
// is the conserved grand total for an item — must equal the sum of its balances.
$fullLedger = static function (int $i) use ($pdo): float {
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE
            WHEN type IN ('in','release','transfer_in') THEN qty
            WHEN type IN ('reserve','transfer_out') THEN -qty
            WHEN type = 'adjustment' THEN qty
            ELSE 0 END), 0)
         FROM stock_movements WHERE item_id = ?"
    );
    $st->execute([$i]);
    return (float) $st->fetchColumn();
};
$sumBalances = static function (int $i) use ($pdo): float {
    $st = $pdo->prepare('SELECT COALESCE(SUM(qty), 0) FROM stock_balances WHERE item_id = ?');
    $st->execute([$i]);
    return (float) $st->fetchColumn();
};
// Create a fresh item stocked with $qty at the main warehouse (ledger + caches).
$makeItem = static function (string $name, string $qty) use ($pdo, $items): int {
    $st = $pdo->prepare(
        "INSERT INTO warehouse_items (name, sku, unit, qty_in_stock, reorder_level, unit_cost, is_active)
         VALUES (?, ?, 'pcs', 0, 0, NULL, 1)"
    );
    $st->execute([$name, 'MS-' . substr(md5($name . microtime()), 0, 8)]);
    $id = (int) $pdo->lastInsertId();
    $mv = $pdo->prepare(
        "INSERT INTO stock_movements (item_id, location_id, type, qty, user_id, note)
         VALUES (?, 1, 'in', ?, 1, 'Giacenza iniziale test')"
    );
    $mv->execute([$id, $qty]);
    $items->refreshCaches($id, 1);
    return $id;
};

// ---------------------------------------------------------------------------
T::section('Multi-site: project auto-site location');
$site = $loc->findForProject(1);
T::ok($site !== null, 'project 1 has an auto-created site location');
$siteId = (int) ($site['id'] ?? 0);
T::equals('site', (string) ($site['kind'] ?? ''), 'the location kind is "site"');

// ---------------------------------------------------------------------------
T::section('Multi-site: warehouse -> cantiere transfer');
$itemA = $makeItem('MS Cemento', '200');
T::equals(200.0, $balOf($itemA, $WAREHOUSE), 'fresh item starts with 200 at the warehouse');
T::equals(0.0, $balOf($itemA, $siteId), 'and 0 at the site');
T::equals(200.0, $stockOf($itemA), 'qty_in_stock == warehouse balance');

$fullBefore = $fullLedger($itemA);
$xfer->transfer($itemA, $WAREHOUSE, $siteId, '50', $ADMIN, 'Trasferimento test');
T::equals(150.0, $balOf($itemA, $WAREHOUSE), 'warehouse balance -50');
T::equals(50.0, $balOf($itemA, $siteId), 'site balance +50');
T::equals(150.0, $stockOf($itemA), 'qty_in_stock follows the warehouse balance down');
T::equals($fullBefore, $fullLedger($itemA), 'transfer conserves the item total across locations');
T::equals($fullLedger($itemA), $sumBalances($itemA), 'sum of location balances == full-ledger recompute');

// ---------------------------------------------------------------------------
T::section('Multi-site: transfer validation');
T::throws(static fn () => $xfer->transfer($itemA, $siteId, $WAREHOUSE, '999999', $ADMIN, null), 'oversize transfer from site blocked (negative-stock guard)');
T::equals(50.0, $balOf($itemA, $siteId), 'blocked transfer left the site balance untouched');
T::throws(static fn () => $xfer->transfer($itemA, $WAREHOUSE, $WAREHOUSE, '1', $ADMIN, null), 'same source and destination rejected');
T::throws(static fn () => $xfer->transfer($itemA, $WAREHOUSE, $siteId, '0', $ADMIN, null), 'zero quantity rejected');
T::throws(static fn () => $xfer->transfer($itemA, $WAREHOUSE, $siteId, '-5', $ADMIN, null), 'negative quantity rejected');

// ---------------------------------------------------------------------------
T::section('Multi-site: reserve -> complete keeps caches == ledger');
$itemB  = $makeItem('MS Calce', '800');
$before = $stockOf($itemB);
$iv = $svc->create(
    ['project_id' => 1, 'assigned_worker_id' => $WORKER, 'title' => 'MS reserve',
     'description' => null, 'scheduled_date' => date('Y-m-d'), 'scheduled_start_time' => null],
    [['item_id' => $itemB, 'qty_planned' => '30']],
    $ADMIN
);
T::equals($before - 30, $stockOf($itemB), 'reservation decremented the warehouse');
T::equals($balOf($itemB, $WAREHOUSE), $stockOf($itemB), 'balance cache == qty_in_stock after reserve');
$svc->transition($iv, 'in_progress', $WORKER);
$pdo->exec(
    "INSERT INTO photos (intervention_id, project_id, type, file_path, uploaded_by)
     SELECT id, project_id, 'after', 'test/after.jpg', 1 FROM interventions WHERE id = {$iv}"
);
$midB = (int) $pdo->query("SELECT id FROM intervention_materials WHERE intervention_id = {$iv}")->fetchColumn();
$svc->complete($iv, $WORKER, [$midB => '25'], 'Note finali');
T::equals($before - 25, $stockOf($itemB), 'net consumption == qty_used (reserve corrected by release)');
T::equals($fullLedger($itemB), $sumBalances($itemB), 'sum(balances) == full ledger after completion');
T::equals($balOf($itemB, $WAREHOUSE), $stockOf($itemB), 'balance cache == qty_in_stock after completion');

// ---------------------------------------------------------------------------
T::section('Multi-site: complete() does NOT inflate stock for never-reserved materials (bug fix)');
// Reproduce the seed/import condition: an is_reserved=0 material with no reserve movement.
$itemC  = $makeItem('MS Acciaio', '500');
$before = $stockOf($itemC);
$pdo->exec("INSERT INTO interventions (project_id, assigned_worker_id, title, status, started_at) VALUES (1, {$WORKER}, 'MS non prenotato', 'in_progress', NOW())");
$ivBug = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO intervention_materials (intervention_id, item_id, qty_planned, qty_used, is_reserved) VALUES ({$ivBug}, {$itemC}, '40', NULL, 0)");
$pdo->exec("INSERT INTO photos (intervention_id, project_id, type, file_path, uploaded_by) VALUES ({$ivBug}, 1, 'after', 'test/after.jpg', 1)");
$midC = (int) $pdo->query("SELECT id FROM intervention_materials WHERE intervention_id = {$ivBug}")->fetchColumn();
$svc->complete($ivBug, $WORKER, [$midC => '0'], 'Nulla utilizzato');
T::equals($before, $stockOf($itemC), 'never-reserved material does not inflate warehouse stock');
$releaseRows = (int) $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE intervention_id = {$ivBug} AND type = 'release'")->fetchColumn();
T::equals(0, $releaseRows, 'no phantom release movement emitted for the never-reserved material');
T::equals($fullLedger($itemC), $sumBalances($itemC), 'cache still equals ledger after the non-inflating completion');
