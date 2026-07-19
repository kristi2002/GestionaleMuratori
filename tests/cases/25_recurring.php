<?php
/**
 * Recurring interventions (migration 027): the scheduler materialises real
 * interventions from a due plan and advances next_run_date; re-running the same
 * day generates nothing (idempotent). In-process; cleans up what it creates.
 */
declare(strict_types=1);

use App\Models\RecurringInterventionModel;
use App\Services\SchedulerService;

/** @var PDO $pdo */

T::section('Recurring interventions: generation + idempotency');

$today   = date('Y-m-d');
$nextWk  = date('Y-m-d', strtotime($today . ' +1 week'));
$proj    = (int) $pdo->query('SELECT id FROM projects ORDER BY id LIMIT 1')->fetchColumn();
$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
$marker  = 'RECURTEST-PLAN';

$model = new RecurringInterventionModel();
$recId = $model->create([
    'project_id'           => $proj,
    'assigned_worker_id'   => null,
    'title'                => $marker,
    'description'          => 'Manutenzione programmata',
    'frequency'            => 'weekly',
    'interval_count'       => 1,
    'scheduled_start_time' => '08:00',
    'start_date'           => $today,
    'next_run_date'        => $today,
    'end_date'             => null,
    'created_by'           => $adminId,
]);

$before    = (int) $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = " . $pdo->quote($marker))->fetchColumn();
// Only the recurring generation (not the full run) so notification state is untouched.
$generated = (new SchedulerService())->generateRecurring($today);
T::ok($generated >= 1, 'scheduler reports a generated recurring intervention');

$after = (int) $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = " . $pdo->quote($marker))->fetchColumn();
T::equals($before + 1, $after, 'exactly one occurrence generated for today');

$iv = $pdo->query("SELECT * FROM interventions WHERE title = " . $pdo->quote($marker) . " ORDER BY id DESC LIMIT 1")->fetch();
T::equals($today, (string) $iv['scheduled_date'], 'occurrence scheduled on the due date');
T::equals($proj, (int) $iv['project_id'], 'occurrence created on the plan project');
T::equals('pending', (string) $iv['status'], 'occurrence starts pending');

T::equals($nextWk, (string) $pdo->query("SELECT next_run_date FROM recurring_interventions WHERE id = {$recId}")->fetchColumn(),
    'next_run_date advanced by one week');

// Idempotent: a same-day re-run generates nothing new.
(new SchedulerService())->generateRecurring($today);
T::equals($after, (int) $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = " . $pdo->quote($marker))->fetchColumn(),
    'second run generates no duplicates');

// A plan past its end_date self-deactivates and generates nothing.
$ended = $model->create([
    'project_id' => $proj, 'assigned_worker_id' => null, 'title' => $marker, 'description' => null,
    'frequency' => 'weekly', 'interval_count' => 1, 'scheduled_start_time' => null,
    'start_date' => date('Y-m-d', strtotime($today . ' -30 days')),
    'next_run_date' => $today,
    'end_date' => date('Y-m-d', strtotime($today . ' -1 day')),
    'created_by' => $adminId,
]);
$countBefore = (int) $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = " . $pdo->quote($marker))->fetchColumn();
(new SchedulerService())->generateRecurring($today);
T::equals($countBefore, (int) $pdo->query("SELECT COUNT(*) FROM interventions WHERE title = " . $pdo->quote($marker))->fetchColumn(),
    'an already-ended plan generates nothing');
T::equals(0, (int) $pdo->query("SELECT is_active FROM recurring_interventions WHERE id = {$ended}")->fetchColumn(),
    'an ended plan is deactivated');

// Teardown: remove generated occurrences (+ their history) and the plans.
foreach ($pdo->query("SELECT id FROM interventions WHERE title = " . $pdo->quote($marker))->fetchAll(PDO::FETCH_COLUMN) as $iid) {
    $pdo->exec("DELETE FROM intervention_status_history WHERE intervention_id = " . (int) $iid);
    $pdo->exec("DELETE FROM interventions WHERE id = " . (int) $iid);
}
$pdo->exec("DELETE FROM recurring_interventions WHERE title = " . $pdo->quote($marker));
