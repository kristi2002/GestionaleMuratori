<?php
/**
 * Performance pass: pagination counts and N+1 batch loaders return correct data
 * (the render/RBAC of the paginated pages is covered by the HTTP e2e cases).
 */
declare(strict_types=1);

use App\Models\NotificationModel;
use App\Models\PhotoModel;
use App\Models\ProjectSubcontractorModel;

/** @var PDO $pdo */

T::section('Perf: pagination counts');

$nm     = new NotificationModel();
$gTotal = $nm->countAll(false, null);
T::equals($gTotal, count($nm->all(false, null, 1000, 0)), 'NotificationModel::countAll matches all()');
if ($gTotal >= 2) {
    $p1 = $nm->all(false, null, 1, 0);
    $p2 = $nm->all(false, null, 1, 1);
    T::ok(count($p1) === 1 && count($p2) === 1, 'notification pages return the page size');
    T::ok((int) $p1[0]['id'] !== (int) $p2[0]['id'], 'consecutive notification pages do not overlap');
} else {
    T::ok(true, 'not enough global notifications to window (skipped)');
}

T::section('Perf: N+1 batch loaders');

$ivWithPhotos = (int) $pdo->query('SELECT intervention_id FROM photos GROUP BY intervention_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn();
if ($ivWithPhotos > 0) {
    $batch = (new PhotoModel())->forInterventions([$ivWithPhotos, 0]);
    $expected = (int) $pdo->query("SELECT COUNT(*) FROM photos WHERE intervention_id = {$ivWithPhotos}")->fetchColumn();
    T::equals($expected, count($batch[$ivWithPhotos] ?? []), 'forInterventions groups all photos for the intervention');
} else {
    T::ok(true, 'no seeded photos (skipped)');
}

$sub = (int) $pdo->query('SELECT subcontractor_id FROM project_subcontractors GROUP BY subcontractor_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn();
if ($sub > 0) {
    $model  = new ProjectSubcontractorModel();
    $batch  = $model->projectIdsForMany([$sub]);
    $single = $model->projectIdsFor($sub);
    sort($single);
    $b = $batch[$sub] ?? [];
    sort($b);
    T::equals($single, $b, 'projectIdsForMany matches the per-row query');
} else {
    T::ok(true, 'no project-subcontractor links (skipped)');
}
