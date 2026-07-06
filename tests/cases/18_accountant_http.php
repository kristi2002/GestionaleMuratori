<?php
/**
 * Accountant export (v2 Phase 6): monthly material cost (qty × unit_cost) and
 * worker hours (from attendance), plus the .xlsx download. Runs in-process, so it
 * exercises both the aggregation service and the HTTP endpoint.
 */
declare(strict_types=1);

use App\Services\Report\AccountantExportDataService;

/** @var PDO $pdo */
/** @var string $baseUrl */

// --- Seed a known month of activity (February 2026) --------------------------
$item = (int) $pdo->query("SELECT id FROM warehouse_items WHERE unit_cost IS NOT NULL ORDER BY id LIMIT 1")->fetchColumn();
$unitCost = (float) $pdo->query("SELECT unit_cost FROM warehouse_items WHERE id = {$item}")->fetchColumn();
$iv = (int) $pdo->query("SELECT id FROM interventions ORDER BY id LIMIT 1")->fetchColumn();

// 10 units consumed (ledger 'out') in February, valued at unit_cost.
$pdo->prepare(
    "INSERT INTO stock_movements (item_id, location_id, type, qty, intervention_id, user_id, note, created_at)
     VALUES (?, 1, 'out', '10', ?, 1, 'test acct', '2026-02-15 10:00:00')"
)->execute([$item, $iv]);

// A closed 9-hour attendance shift in February for worker2 (id 3).
$pdo->prepare(
    "INSERT INTO site_attendance (project_id, user_id, person_name, entry_at, exit_at)
     VALUES (1, 3, 'Giuseppe Muratore', '2026-02-10 08:00:00', '2026-02-10 17:00:00')"
)->execute();

// ---------------------------------------------------------------------------
T::section('Accountant export: aggregation service');
$data = (new AccountantExportDataService())->build('2026-02-01 00:00:00', '2026-03-01 00:00:00');

$mine = array_values(array_filter($data['materials'], static fn ($m) => (float) $m['total_qty'] >= 10));
T::ok($mine !== [], 'material consumption picked up for the month');
$expectedCost = round(10 * $unitCost, 2);
T::ok(abs((float) $mine[0]['total_cost'] - $expectedCost) < 0.001, 'material cost = qty × unit_cost');

$hours = array_sum(array_map(static fn ($l) => (float) $l['hours'], $data['labor']));
T::ok($hours >= 9.0, 'worker hours include the 9-hour February shift');
T::ok($data['projects'] !== [], 'per-cantiere cost breakdown produced');
T::ok($data['totals']['material_cost'] >= $expectedCost, 'total material cost aggregated');

// A different month sees none of this activity.
$empty = (new AccountantExportDataService())->build('2025-01-01 00:00:00', '2025-02-01 00:00:00');
T::equals(0.0, (float) $empty['totals']['material_cost'], 'unrelated month has zero material cost');

// ---------------------------------------------------------------------------
T::section('E2E: accountant .xlsx download');
$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
T::equals(403, $worker->get('/admin/exports', ['json' => false])['status'], 'worker blocked from exports');

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
T::equals(200, $admin->get('/admin/exports', ['json' => false])['status'], 'admin sees the exports page');

$xlsx = $admin->get('/admin/exports/accountant?month=2026-02', ['json' => false]);
T::equals(200, $xlsx['status'], 'accountant workbook downloads');
T::ok(str_starts_with($xlsx['body'], 'PK'), 'response is a real .xlsx (ZIP magic bytes)');
T::ok(strlen($xlsx['body']) > 2000, 'workbook has substantial content');

// Invalid month falls back to the current month rather than erroring.
T::equals(200, $admin->get('/admin/exports/accountant?month=not-a-month', ['json' => false])['status'], 'invalid month falls back gracefully');
