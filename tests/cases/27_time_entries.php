<?php
/**
 * Intervention work timers (migration 029): start/stop, single running timer per
 * worker, total seconds, per-worker rollup. In-process; cleans up.
 */
declare(strict_types=1);

use App\Models\InterventionTimeEntryModel;

/** @var PDO $pdo */

T::section('Intervention timer: start/stop model');

$ivId     = (int) $pdo->query('SELECT id FROM interventions ORDER BY id LIMIT 1')->fetchColumn();
$workerId = (int) $pdo->query("SELECT id FROM users WHERE role = 'worker' ORDER BY id LIMIT 1")->fetchColumn();

$m    = new InterventionTimeEntryModel();
$base = $m->totalSeconds($ivId);
T::ok($m->runningForUser($workerId) === null, 'no running timer initially');

$eid = $m->start($ivId, $workerId);
$run = $m->runningForUser($workerId);
T::ok($run !== null && (int) $run['id'] === $eid, 'runningForUser returns the started entry');
T::equals($ivId, (int) $run['intervention_id'], 'running entry is on the right intervention');
T::ok(($run['intervention_title'] ?? null) !== null, 'running entry carries the job title');

T::ok($m->stop($ivId, $workerId) === true, 'stop closes the running entry');
T::ok($m->runningForUser($workerId) === null, 'no running timer after stop');
T::ok($m->stop($ivId, $workerId) === false, 'stopping again is a no-op');
T::ok($m->totalSeconds($ivId) >= $base, 'total seconds never decreases');

$pw = $m->perWorker($ivId);
T::ok(count(array_filter($pw, static fn ($r) => (int) $r['seconds'] >= 0)) >= 1, 'perWorker returns a rollup row');

// Teardown.
$pdo->exec("DELETE FROM intervention_time_entries WHERE intervention_id = {$ivId} AND user_id = {$workerId}");
T::equals($base, $m->totalSeconds($ivId), 'teardown restores the baseline');
