<?php
/**
 * Buoni d'Ordine: receiving a delivery (DDT) into stock. Verifies that the receipt
 * writes type='in' ledger rows tagged with the purchase_order_line_id, keeps the stock
 * caches equal to the ledger, derives the header status from received-vs-ordered
 * quantities (partial -> full), allows over-receipt with a warning, and refuses to
 * receive against a cancelled order. Uses FRESH suppliers/items so it stays isolated
 * from the seeded data the other cases share.
 */
declare(strict_types=1);

use App\Models\PurchaseOrderModel;
use App\Models\StockBalanceModel;
use App\Models\StockLocationModel;
use App\Services\PurchaseOrderReceiptService;

/** @var PDO $pdo (from run.php) */

$ADMIN     = 1;
$WAREHOUSE = StockLocationModel::MAIN_WAREHOUSE_ID; // 1

$orders   = new PurchaseOrderModel();
$balances = new StockBalanceModel();
$receipt  = new PurchaseOrderReceiptService();

$stockOf = static function (int $i) use ($pdo): float {
    $st = $pdo->prepare('SELECT qty_in_stock FROM warehouse_items WHERE id = ?');
    $st->execute([$i]);
    return (float) $st->fetchColumn();
};
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
$makeItem = static function (string $name) use ($pdo): int {
    // Fresh item that starts empty (no initial 'in') so received qty is unambiguous.
    $st = $pdo->prepare(
        "INSERT INTO warehouse_items (name, sku, unit, qty_in_stock, reorder_level, unit_cost, is_active)
         VALUES (?, ?, 'pcs', 0, 0, NULL, 1)"
    );
    $st->execute([$name, 'PO-' . substr(md5($name . microtime()), 0, 8)]);
    $id = (int) $pdo->lastInsertId();
    (new StockBalanceModel())->recompute($id, 1);
    return $id;
};

$supplierId = (function () use ($pdo): int {
    $st = $pdo->prepare("INSERT INTO suppliers (name, is_active) VALUES (?, 1)");
    $st->execute(['PO Test Fornitore ' . substr(md5(microtime()), 0, 6)]);
    return (int) $pdo->lastInsertId();
})();

// A PO with one stock line (100), a second stock line (50), and one free line.
$itemA = $makeItem('PO Cemento');
$itemB = $makeItem('PO Calce');
$poId  = $orders->create([
    'supplier_id' => $supplierId, 'project_id' => null, 'location_id' => $WAREHOUSE,
    'number' => 'BO-TEST-' . substr(md5(microtime()), 0, 6), 'title' => 'Ordine test',
    'order_date' => date('Y-m-d'), 'expected_date' => null, 'status' => 'sent',
    'vat_rate' => '22.00', 'notes' => null, 'created_by' => $ADMIN,
], [
    ['item_id' => $itemA, 'description' => 'Cemento', 'qty' => '100', 'unit' => 'sacchi', 'unit_price' => '6.00'],
    ['item_id' => $itemB, 'description' => 'Calce',   'qty' => '50',  'unit' => 'kg',     'unit_price' => '0.30'],
    ['item_id' => null,   'description' => 'Trasporto', 'qty' => '1', 'unit' => 'a corpo', 'unit_price' => '80.00'],
]);

$lineId = static function (int $poId, int $itemId) use ($pdo): int {
    $st = $pdo->prepare('SELECT id FROM purchase_order_lines WHERE purchase_order_id = ? AND item_id = ?');
    $st->execute([$poId, $itemId]);
    return (int) $st->fetchColumn();
};
$lineA = $lineId($poId, $itemA);
$lineB = $lineId($poId, $itemB);

// ---------------------------------------------------------------------------
T::section('Purchase order: partial receipt');
$receipt->receive($poId, [$lineA => '40', $lineB => '50'], $ADMIN);
T::equals(40.0, $stockOf($itemA), 'item A stock +40 after partial receipt');
T::equals(50.0, $stockOf($itemB), 'item B stock +50 (fully received)');
T::equals($fullLedger($itemA), (float) $balances->qty($itemA, $WAREHOUSE), 'item A balance cache == ledger');
T::equals('partially_received', (string) $orders->find($poId)['status'], 'header is partially_received (line A still open)');

// ---------------------------------------------------------------------------
T::section('Purchase order: received quantity is summed from the ledger');
$rows = [];
foreach ($orders->linesWithReceived($poId) as $l) {
    $rows[(int) $l['id']] = (float) $l['qty_received'];
}
T::equals(40.0, $rows[$lineA], 'line A qty_received derived = 40');
T::equals(50.0, $rows[$lineB], 'line B qty_received derived = 50');

// ---------------------------------------------------------------------------
T::section('Purchase order: second receipt completes the order');
$receipt->receive($poId, [$lineA => '60'], $ADMIN);
T::equals(100.0, $stockOf($itemA), 'item A stock now 100 (40 + 60)');
T::equals('received', (string) $orders->find($poId)['status'], 'header flips to received when every stock line is full');
T::ok($orders->hasReceipts($poId), 'hasReceipts() true once deliveries exist (locks edit/delete)');

// ---------------------------------------------------------------------------
T::section('Purchase order: over-receipt is allowed but warned');
$itemC = $makeItem('PO Sabbia');
$poC   = $orders->create([
    'supplier_id' => $supplierId, 'project_id' => null, 'location_id' => $WAREHOUSE,
    'number' => 'BO-OVER-' . substr(md5(microtime()), 0, 6), 'title' => 'Over test',
    'order_date' => date('Y-m-d'), 'expected_date' => null, 'status' => 'confirmed',
    'vat_rate' => '22.00', 'notes' => null, 'created_by' => $ADMIN,
], [
    ['item_id' => $itemC, 'description' => 'Sabbia', 'qty' => '10', 'unit' => 'kg', 'unit_price' => '0.05'],
]);
$lineC  = $lineId($poC, $itemC);
$result = $receipt->receive($poC, [$lineC => '15'], $ADMIN);
T::equals(15.0, $stockOf($itemC), 'over-delivery (15 of 10) still lands in stock');
T::equals('received', (string) $orders->find($poC)['status'], 'over-received order counts as received');
T::ok($result['over'] !== [], 'over-receipt reported as a warning');

// ---------------------------------------------------------------------------
T::section('Purchase order: receipt validation guards');
$poCancelled = $orders->create([
    'supplier_id' => $supplierId, 'project_id' => null, 'location_id' => $WAREHOUSE,
    'number' => 'BO-CANC-' . substr(md5(microtime()), 0, 6), 'title' => 'Annullato',
    'order_date' => date('Y-m-d'), 'expected_date' => null, 'status' => 'cancelled',
    'vat_rate' => '22.00', 'notes' => null, 'created_by' => $ADMIN,
], [
    ['item_id' => $itemA, 'description' => 'Cemento', 'qty' => '5', 'unit' => 'sacchi', 'unit_price' => '6.00'],
]);
$cancLine = $lineId($poCancelled, $itemA);
T::throws(static fn () => $receipt->receive($poCancelled, [$cancLine => '5'], $ADMIN), 'cannot receive against a cancelled order');
T::throws(static fn () => $receipt->receive($poId, [$lineA => '0'], $ADMIN), 'a receipt with nothing to book is rejected');
T::throws(static fn () => $receipt->receive($poId, [999999 => '5'], $ADMIN), 'a line id from another order is rejected');
