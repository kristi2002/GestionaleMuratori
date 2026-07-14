<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */
/** @var int $projectId */
/** @var array<int,array<string,mixed>> $logs */
/** @var string $today */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Cheap, real stat strip computed from the already-loaded day list.
$totalLogs  = count($logs);
$openLogs   = 0;
foreach ($logs as $l) {
    if ((int) $l['is_closed'] === 0) { $openLogs++; }
}
$closedLogs = $totalLogs - $openLogs;

$actions = '';
if ($projectId > 0) {
    $actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/daily-logs/create?project_id=' . $projectId)) . '">'
        . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.daily_logs.new')) . '</a>';
}

echo View::render('partials/page_head', [
    'title'    => $t('admin.daily_logs.title'),
    'subtitle' => $t('admin.daily_logs.subtitle'),
    'actions'  => $actions,
], null);
?>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-6 col-lg-4">
        <select class="form-select" name="project_id" onchange="this.form.submit()" aria-label="<?= $e($t('admin.daily_logs.project')) ?>">
            <?php foreach ($projects as $p): ?>
                <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($totalLogs > 0): ?>
    <div class="app-kpi-grid mb-4">
        <div class="card gm-kpi is-primary h-100">
            <i class="bi bi-journal-text gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $totalLogs) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.daily_logs.kpi_total')) ?></div>
        </div>
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-unlock gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $openLogs) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.daily_logs.kpi_open')) ?></div>
        </div>
        <div class="card gm-kpi is-info h-100">
            <i class="bi bi-lock gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $closedLogs) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.daily_logs.kpi_closed')) ?></div>
        </div>
    </div>
<?php endif; ?>

<?php if ($logs === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('admin.daily_logs.empty'),
        'actions' => $projectId > 0
            ? [[$t('admin.daily_logs.new'), '/admin/daily-logs/create?project_id=' . $projectId, 'btn-success']]
            : [],
    ], null) ?>
<?php else: ?>
    <div class="mb-3">
        <?php foreach ($logs as $log): ?>
            <?php $isClosed = (int) $log['is_closed'] === 1; ?>
            <div class="app-timeline-item">
                <span class="app-timeline-icon<?= $isClosed ? ' is-project' : '' ?>">
                    <i class="bi <?= $isClosed ? 'bi-lock' : 'bi-calendar3' ?>" aria-hidden="true"></i>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <span class="mono tnum fw-bold"><?= $e($log['log_date']) ?></span>
                        <?php if ($isClosed): ?>
                            <span class="badge text-bg-secondary"><?= $e($t('admin.daily_logs.closed_badge')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-success"><?= $e($t('admin.daily_logs.open_badge')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1 d-flex flex-wrap gap-3">
                        <span>
                            <i class="bi bi-cloud-sun" aria-hidden="true"></i>
                            <?= $e($log['weather_text'] ?? '—') ?>
                            <?php if ($log['temp_min'] !== null || $log['temp_max'] !== null): ?>
                                (<?= $e((string) $log['temp_min']) ?>° / <?= $e((string) $log['temp_max']) ?>°)
                            <?php endif; ?>
                        </span>
                        <span>
                            <i class="bi bi-people" aria-hidden="true"></i>
                            <?= $e($log['workers_present'] !== null ? (string) $log['workers_present'] : '—') ?>
                            <?= $e($t('admin.daily_logs.workers')) ?>
                        </span>
                    </div>
                </div>
                <a class="btn btn-sm btn-outline-secondary align-self-center" href="<?= $e(Url::to('/admin/daily-logs/' . $log['id'])) ?>">
                    <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('admin.daily_logs.open')) ?>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
