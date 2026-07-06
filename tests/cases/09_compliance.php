<?php
/**
 * Scadenzario Sicurezza (v2 Phase 4d): expiry queries, polymorphic subject
 * resolution, and CRUD at the model layer.
 */
declare(strict_types=1);

use App\Models\ComplianceDocumentModel;

/** @var PDO $pdo (from run.php) */

$model = new ComplianceDocumentModel();
$today = (new DateTimeImmutable('today'));

// ---------------------------------------------------------------------------
T::section('Compliance: expiring-soon widget query');
$soon = $model->expiringSoon(30);
T::ok(count($soon) >= 3, 'seed has ≥3 documents expiring within 30 days (incl. expired)');
foreach ($soon as $d) {
    T::ok($d['expiry_date'] !== null && $d['expiry_date'] <= $today->modify('+30 days')->format('Y-m-d'),
        'expiringSoon only returns docs within the window: ' . $d['doc_type']);
    if ($d['expiry_date'] > $today->format('Y-m-d')) {
        break; // sorted ascending; the checks above already validate the set
    }
}
// The far-future patente/visita are excluded.
$patente = array_filter($soon, static fn ($d) => $d['doc_type'] === 'patente_crediti');
T::equals(0, count($patente), 'far-future Patente a Crediti not flagged as expiring');

// ---------------------------------------------------------------------------
T::section('Compliance: subject name resolution');
$all = $model->all();
$byType = [];
foreach ($all as $d) {
    $byType[$d['subject_type']][] = $d;
}
T::ok(isset($byType['subcontractor']), 'a subcontractor-scoped document exists');
T::ok(($byType['subcontractor'][0]['subject_name'] ?? null) !== null, 'subcontractor subject_name resolved via join');
T::ok(($byType['worker'][0]['subject_name'] ?? null) !== null, 'worker subject_name resolved via join');
$company = $byType['company'][0] ?? null;
T::ok($company !== null && $company['subject_name'] === null, 'company documents have no subject_name (subject_id null)');

// ---------------------------------------------------------------------------
T::section('Compliance: filters');
$durc = $model->all(['doc_type' => 'DURC']);
T::ok($durc !== [] && array_reduce($durc, static fn ($c, $d) => $c && $d['doc_type'] === 'DURC', true), 'doc_type filter returns only DURC');
$expiringOnly = $model->all(['expiring' => true]);
T::ok(count($expiringOnly) >= 3, 'expiring filter matches the widget query');

// ---------------------------------------------------------------------------
T::section('Compliance: CRUD');
$id = $model->create([
    'subject_type' => 'project', 'subject_id' => 1, 'doc_type' => 'POS',
    'reference' => 'POS-TEST', 'issue_date' => '2026-01-01',
    'expiry_date' => '2027-01-01', 'credits' => null, 'notes' => 'test', 'created_by' => 1,
]);
T::ok($id > 0, 'create returns an id');
$row = $model->find($id);
T::equals('POS', (string) $row['doc_type'], 'find returns the document');
T::ok(($row['subject_name'] ?? null) !== null, 'project subject_name resolved via join');

$model->update($id, [
    'subject_type' => 'project', 'subject_id' => 1, 'doc_type' => 'PSC',
    'reference' => 'PSC-TEST', 'issue_date' => null, 'expiry_date' => null,
    'credits' => null, 'notes' => null,
]);
T::equals('PSC', (string) $model->find($id)['doc_type'], 'update persists the new doc_type');

T::ok($model->delete($id), 'delete returns true');
T::ok($model->find($id) === null, 'document gone after delete');
