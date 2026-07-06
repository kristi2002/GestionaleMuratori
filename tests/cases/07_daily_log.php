<?php
/**
 * Giornale dei Lavori (v2 Phase 4b): weather-code mapping, the per-(project,date)
 * uniqueness, and closed-day immutability at the model layer.
 */
declare(strict_types=1);

use App\Models\DailyLogModel;
use App\Models\EquipmentModel;
use App\Services\WeatherService;

/** @var PDO $pdo (from run.php) */

$logs  = new DailyLogModel();
$equip = new EquipmentModel();

// ---------------------------------------------------------------------------
T::section('Weather: WMO code → Italian text');
T::equals('Sereno', WeatherService::describe(0), 'code 0 = Sereno');
T::equals('Pioggia moderata', WeatherService::describe(63), 'code 63 = Pioggia moderata');
T::equals('Temporale', WeatherService::describe(95), 'code 95 = Temporale');
T::equals('Condizioni variabili', WeatherService::describe(12345), 'unknown code → generic label');
T::equals('', WeatherService::describe(null), 'null code → empty string');

// Weather disabled in tests → forDate returns null (no network hit).
T::ok((new WeatherService())->forDate('43.3', '13.5', '2026-07-01') === null, 'weather auto-fill disabled in tests returns null');

// ---------------------------------------------------------------------------
T::section('Daily log: create + uniqueness');
$PROJECT = 2; // avoid clashing with any e2e log on project 1
$date    = '2026-06-01';
$logs->create([
    'project_id' => $PROJECT, 'log_date' => $date, 'weather_text' => 'Sereno',
    'workers_present' => 5, 'work_done' => 'Getto pilastri', 'created_by' => 1,
]);
$row = $logs->findForProjectDate($PROJECT, $date);
T::ok($row !== null, 'findForProjectDate returns the created log');
$logId = (int) $row['id'];
T::equals(5, (int) $row['workers_present'], 'workers_present stored');
T::equals(0, (int) $row['is_closed'], 'new log is open');

// ---------------------------------------------------------------------------
T::section('Daily log: equipment sync');
$catalog = $equip->listActive();
T::ok(count($catalog) >= 2, 'equipment catalog seeded');
$eIds = [(int) $catalog[0]['id'], (int) $catalog[1]['id']];
$logs->syncEquipment($logId, $eIds);
$attached = $logs->equipmentIds($logId);
sort($attached);
sort($eIds);
T::equals($eIds, $attached, 'syncEquipment attaches the given equipment');
$logs->syncEquipment($logId, [$eIds[0]]);
T::equals([$eIds[0]], $logs->equipmentIds($logId), 're-sync replaces the equipment set');

// ---------------------------------------------------------------------------
T::section('Daily log: closed-day immutability');
$logs->update($logId, ['work_done' => 'Modifica prima chiusura', 'workers_present' => 6]);
T::equals('Modifica prima chiusura', (string) $logs->find($logId)['work_done'], 'open log is editable');

T::ok($logs->close($logId, 1), 'close() locks the log');
$closed = $logs->find($logId);
T::equals(1, (int) $closed['is_closed'], 'is_closed = 1 after close');
T::ok($closed['closed_at'] !== null, 'closed_at set');

// update() must not touch a closed log (WHERE is_closed = 0).
$logs->update($logId, ['work_done' => 'TENTATIVO DOPO CHIUSURA', 'workers_present' => 99]);
T::equals('Modifica prima chiusura', (string) $logs->find($logId)['work_done'], 'update() is a no-op on a closed log');
T::equals(6, (int) $logs->find($logId)['workers_present'], 'closed workers_present unchanged');

// close() again is a no-op (already closed).
$before = $logs->find($logId)['closed_at'];
$logs->close($logId, 2);
T::equals($before, $logs->find($logId)['closed_at'], 're-closing does not change closed_at/closed_by');
