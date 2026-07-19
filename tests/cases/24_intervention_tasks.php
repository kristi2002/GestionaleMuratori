<?php
/**
 * Intervention checklist model (migration 026): create/list/progress and the
 * ABSOLUTE, idempotent setDone() (safe to replay an offline-queued toggle).
 * In-process; inserts a couple of items and cleans up.
 */
declare(strict_types=1);

use App\Models\InterventionTaskModel;

/** @var PDO $pdo */

T::section('Intervention checklist: model + idempotent toggle');

$ivId    = (int) $pdo->query('SELECT id FROM interventions ORDER BY id LIMIT 1')->fetchColumn();
$adminId = (int) $pdo->query("SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();

$m    = new InterventionTaskModel();
$base = $m->progressForIntervention($ivId);

$t1 = $m->create(['intervention_id' => $ivId, 'label' => 'Scrostatura', 'created_by' => $adminId]);
$t2 = $m->create(['intervention_id' => $ivId, 'label' => 'Rinzaffo', 'created_by' => $adminId]);
T::equals($base['total'] + 2, $m->progressForIntervention($ivId)['total'], 'two items added to the checklist');

// position auto-increments, items are ordered.
$items = $m->forIntervention($ivId);
$mine  = array_values(array_filter($items, static fn ($r) => in_array((int) $r['id'], [$t1, $t2], true)));
T::ok((int) $mine[0]['position'] < (int) $mine[1]['position'], 'position auto-increments in insert order');

// setDone is an absolute set — replaying it must not double-count.
$m->setDone($t1, true, $adminId);
$m->setDone($t1, true, $adminId);
T::equals($base['done'] + 1, $m->progressForIntervention($ivId)['done'], 'repeated setDone(true) counts once (idempotent)');
$row = $m->find($t1);
T::ok((int) $row['is_done'] === 1 && $row['done_by'] !== null && $row['done_at'] !== null, 'done stamps who + when');

$m->setDone($t1, false, $adminId);
$doneRow = $m->find($t1);
T::ok((int) $doneRow['is_done'] === 0 && $doneRow['done_by'] === null && $doneRow['done_at'] === null, 'untick clears the stamp');

// Batch progress (list pages) matches the per-item query.
$batch = $m->progressForInterventions([$ivId]);
T::equals($m->progressForIntervention($ivId)['total'], $batch[$ivId]['total'] ?? -1, 'batch progress matches single-item progress');

// Teardown.
$m->delete($t1);
$m->delete($t2);
T::equals($base['total'], $m->progressForIntervention($ivId)['total'], 'teardown restores the baseline');
