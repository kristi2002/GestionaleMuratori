<?php
/**
 * Labor-hours costing (migration 025): hours × rate from site_attendance, folded
 * into the project P&L. In-process; inserts a couple of closed shifts, asserts the
 * computed cost as a DELTA (robust to any seed attendance), then cleans up.
 */
declare(strict_types=1);

use App\Models\SubcontractorModel;
use App\Models\UserModel;
use App\Services\FinancialsService;
use App\Services\LaborCostService;

/** @var PDO $pdo */

T::section('Labor cost: hours × rate, folded into P&L');

$workerId  = (int) $pdo->query("SELECT id FROM users WHERE role = 'worker' ORDER BY id LIMIT 1")->fetchColumn();
$projectId = (int) $pdo->query('SELECT id FROM projects ORDER BY id LIMIT 1')->fetchColumn();

// Give the worker a known rate, then measure the cost contribution of shifts we add.
$pdo->prepare('UPDATE users SET hourly_rate = 20.00 WHERE id = ?')->execute([$workerId]);
$labor    = new LaborCostService();
$baseline = $labor->costForProject($projectId);

$ins = $pdo->prepare(
    'INSERT INTO site_attendance (project_id, user_id, person_name, entry_at, exit_at)
     VALUES (?, ?, ?, ?, ?)'
);
$ins->execute([$projectId, $workerId, 'LABTEST', '2026-06-01 08:00:00', '2026-06-01 10:00:00']); // 2h
$ins->execute([$projectId, $workerId, 'LABTEST', '2026-06-02 08:00:00', '2026-06-02 11:00:00']); // 3h
// An OPEN shift (no exit) must be ignored by the cost math.
$ins->execute([$projectId, $workerId, 'LABTEST', '2026-06-03 08:00:00', null]);

$after = $labor->costForProject($projectId);
T::ok(abs(($after - $baseline) - 100.0) < 0.01, '5h @ €20 adds €100 (open shift ignored)');

$byProject = $labor->costByProject();
T::ok(isset($byProject[$projectId]), 'costByProject includes the project');

// FinancialsService folds labor into the project cost + margin.
$fin = (new FinancialsService())->forProject($projectId);
T::ok(array_key_exists('labor_cost', $fin), 'financials exposes labor_cost');
T::ok(abs((float) $fin['labor_cost'] - $after) < 0.01, 'project labor_cost matches the labor service');
T::ok((float) $fin['cost'] >= (float) $fin['labor_cost'] - 0.01, 'labor is part of total cost');

// Summary lists the project and the person with the added hours.
$summary = $labor->summary();
T::ok($summary['any_rate'] === true, 'summary flags that a rate is configured');
$projRow = null;
foreach ($summary['projects'] as $p) {
    if ((int) $p['id'] === $projectId) { $projRow = $p; break; }
}
T::ok($projRow !== null && (float) $projRow['hours'] >= 5.0, 'project appears in the per-cantiere breakdown with its hours');

// Teardown: remove the test shifts and the rate so later phases see clean data.
$pdo->prepare("DELETE FROM site_attendance WHERE person_name = 'LABTEST'")->execute();
$pdo->prepare('UPDATE users SET hourly_rate = NULL WHERE id = ?')->execute([$workerId]);
T::ok(abs($labor->costForProject($projectId) - $baseline) < 0.01, 'teardown restored the baseline');

// --- Rate persistence via the models ----------------------------------------
T::section('Labor cost: rate persistence');
$um = new UserModel();
$um->update($workerId, [
    'name' => (string) $pdo->query("SELECT name FROM users WHERE id = {$workerId}")->fetchColumn(),
    'email' => (string) $pdo->query("SELECT email FROM users WHERE id = {$workerId}")->fetchColumn(),
    'role' => 'worker', 'client_id' => null, 'subcontractor_id' => null, 'hourly_rate' => 18.75,
]);
T::equals('18.75', (string) $pdo->query("SELECT hourly_rate FROM users WHERE id = {$workerId}")->fetchColumn(), 'worker hourly_rate persists');
$pdo->prepare('UPDATE users SET hourly_rate = NULL WHERE id = ?')->execute([$workerId]);

$subId = (int) $pdo->query('SELECT id FROM subcontractors ORDER BY id LIMIT 1')->fetchColumn();
if ($subId > 0) {
    $sm  = new SubcontractorModel();
    $sub = (new SubcontractorModel())->find($subId);
    $sm->update($subId, [
        'name' => (string) $sub['name'], 'vat_or_tax_id' => $sub['vat_or_tax_id'],
        'email' => $sub['email'], 'phone' => $sub['phone'], 'notes' => $sub['notes'],
        'hourly_rate' => 45.00,
    ]);
    T::equals('45.00', (string) $pdo->query("SELECT hourly_rate FROM subcontractors WHERE id = {$subId}")->fetchColumn(), 'subcontractor hourly_rate persists');
    $pdo->prepare('UPDATE subcontractors SET hourly_rate = NULL WHERE id = ?')->execute([$subId]);
}
