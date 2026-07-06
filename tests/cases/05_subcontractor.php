<?php
/**
 * Subcontractors (v2 Phase 3): CRUD, M:N project assignment, and the portal
 * ownership rule (assigned projects only). Uses fresh rows to stay isolated from
 * the shared seeded database.
 */
declare(strict_types=1);

use App\Models\ProjectSubcontractorModel;
use App\Models\SubcontractorModel;

/** @var PDO $pdo (from run.php) */

$subs  = new SubcontractorModel();
$links = new ProjectSubcontractorModel();

// ---------------------------------------------------------------------------
T::section('Subcontractor: seed baseline');
$seeded = $subs->all('Impianti Marche');
T::ok($seeded !== [], 'seed created the Impianti Marche subcontractor');
$seedId = (int) $seeded[0]['id'];
T::ok($links->isAssigned($seedId, 1), 'seed subcontractor is assigned to project 1');
$subUser = $pdo->query("SELECT id, role, subcontractor_id FROM users WHERE email = 'sub1@gestionale.local'")->fetch();
T::ok($subUser !== false, 'seed created the subcontractor login sub1@gestionale.local');
T::equals('subcontractor', (string) ($subUser['role'] ?? ''), 'the login has role=subcontractor');
T::equals($seedId, (int) ($subUser['subcontractor_id'] ?? 0), 'the login is linked to the subcontractor');

// ---------------------------------------------------------------------------
T::section('Subcontractor: CRUD');
$id = $subs->create([
    'name' => 'Test Subappaltatore', 'vat_or_tax_id' => 'IT99999999999',
    'email' => 'test@sub.local', 'phone' => '+39 000', 'notes' => null,
]);
T::ok($id > 0, 'create returns an id');
$row = $subs->find($id);
T::equals('Test Subappaltatore', (string) ($row['name'] ?? ''), 'find returns the created row');
T::equals(1, (int) ($row['is_active'] ?? 0), 'new subcontractor is active by default');

$subs->update($id, [
    'name' => 'Test Subappaltatore SRL', 'vat_or_tax_id' => 'IT99999999999',
    'email' => 'test@sub.local', 'phone' => null, 'notes' => 'aggiornato',
]);
T::equals('Test Subappaltatore SRL', (string) $subs->find($id)['name'], 'update persists the new name');

$subs->setActive($id, false);
T::equals(0, (int) $subs->find($id)['is_active'], 'setActive(false) deactivates');
$activeNames = array_column($subs->listActive(), 'name');
T::ok(!in_array('Test Subappaltatore SRL', $activeNames, true), 'inactive subcontractor excluded from listActive');
$subs->setActive($id, true);

// ---------------------------------------------------------------------------
T::section('Subcontractor: project assignment (M:N sync)');
$links->syncProjects($id, [2, 3]);
$ids = $links->projectIdsFor($id);
sort($ids);
T::equals([2, 3], $ids, 'syncProjects assigns the given project set');
T::ok($links->isAssigned($id, 2), 'isAssigned true for an assigned project');
T::ok(!$links->isAssigned($id, 4), 'isAssigned false for an unassigned project');
T::equals(2, count($links->projectsFor($id)), 'projectsFor returns full rows for the assigned projects');

// Re-sync replaces (not appends) the whole set, and de-dupes.
$links->syncProjects($id, [4, 4]);
$ids = $links->projectIdsFor($id);
T::equals([4], $ids, 're-sync replaces the previous set and de-dupes');
T::ok(!$links->isAssigned($id, 2), 'previously-assigned project removed after re-sync');

// Empty sync clears all assignments.
$links->syncProjects($id, []);
T::equals([], $links->projectIdsFor($id), 'empty sync clears all assignments');
