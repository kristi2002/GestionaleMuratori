<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */
/** @var int $projectId */
/** @var string $date */
/** @var array<int,array<string,mixed>> $attendance */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$hm = static fn (?string $dt): string => $dt ? substr((string) $dt, 0, 16) : '';
$coord = static function ($lat, $lng) use ($e): string {
    if ($lat === null || $lng === null) {
        return '';
    }
    $q = rawurlencode($lat . ',' . $lng);
    return '<a href="https://www.openstreetmap.org/?mlat=' . $e((string) $lat) . '&mlon=' . $e((string) $lng)
        . '" target="_blank" rel="noopener" title="' . $e($lat . ', ' . $lng) . '">📍</a>';
};
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.attendance.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.attendance.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.attendance.title'), null],
]], null) ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-12 col-sm-5">
                <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.interventions.project')) ?>">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-sm-3">
                <input type="date" class="form-control" name="date" value="<?= $e($date) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.attendance.person')) ?></th>
                    <th><?= $e($t('admin.attendance.company')) ?></th>
                    <th><?= $e($t('admin.attendance.entry')) ?></th>
                    <th><?= $e($t('admin.attendance.exit')) ?></th>
                    <th><?= $e($t('admin.attendance.gps')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($attendance === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.attendance.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($attendance as $a): ?>
                <tr>
                    <td class="fw-medium"><?= $e($a['person_name']) ?></td>
                    <td><?= $e($a['subcontractor_name'] ?? $t('admin.attendance.internal')) ?></td>
                    <td class="mono tnum"><?= $e($hm($a['entry_at'])) ?></td>
                    <td class="mono tnum"><?= $a['exit_at'] !== null ? $e($hm($a['exit_at'])) : '<span class="badge text-bg-success">' . $e($t('attendance.on_site')) . '</span>' ?></td>
                    <td>
                        <?= $coord($a['entry_lat'], $a['entry_lng']) ?>
                        <?= $coord($a['exit_lat'], $a['exit_lng']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
