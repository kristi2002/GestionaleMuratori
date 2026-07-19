<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $open  the caller's open attendance, if clocked in */
/** @var array<int,array<string,mixed>> $projects  projects the caller may clock into */
/** @var array<int,array<string,mixed>> $recent */
/** @var array{days:int,hours:float} $stats */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$hm = static fn (?string $dt): string => $dt ? substr((string) $dt, 0, 16) : '';
$num = static fn (float $n): string => rtrim(rtrim(number_format($n, 1, ',', '.'), '0'), ',');

echo View::render('partials/page_head', [
    'title'    => $t('attendance.title'),
    'subtitle' => $t('attendance.subtitle'),
], null);
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-4">
        <div class="card gm-kpi <?= $open !== null ? 'ok' : '' ?> h-100">
            <i class="bi bi-<?= $open !== null ? 'geo-alt-fill' : 'geo-alt' ?> gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2 fs-4"><?= $e($open !== null ? $t('attendance.on_site') : $t('attendance.off_site')) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('attendance.kpi_status')) ?></div>
            <?php if ($open !== null): ?>
                <div class="gm-kpi-sub text-truncate"><?= $e($open['project_name']) ?></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="card gm-kpi is-info h-100">
            <i class="bi bi-calendar2-check gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $stats['days']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('attendance.kpi_days')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-4">
        <div class="card gm-kpi is-purple h-100">
            <i class="bi bi-clock-history gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($num($stats['hours'])) ?> h</div>
            <div class="gm-kpi-lab"><?= $e($t('attendance.kpi_hours')) ?></div>
        </div>
    </div>
</div>

<div class="alert alert-warning d-none js-offline-queue-banner" role="status"></div>
<?php if (!empty($push_enabled)): ?>
<button type="button" class="btn btn-outline-primary btn-sm mb-3 js-enable-push">
    <i class="bi bi-bell"></i> <?= $e($t('push.enable')) ?>
</button>
<?php endif; ?>
<div class="alert alert-danger d-none js-attendance-error" role="alert"></div>
<div class="alert alert-info d-none js-attendance-geo" role="status"><?= $e($t('attendance.locating')) ?></div>

<div class="card app-record-card mb-3">
    <div class="card-body">
        <?php if ($open !== null): ?>
            <p class="mb-1">
                <span class="badge rounded-pill app-status app-status-success"><?= $e($t('attendance.on_site')) ?></span>
            </p>
            <p class="mb-1"><strong><?= $e($open['project_name']) ?></strong></p>
            <p class="small text-muted mb-3"><?= $e($t('attendance.since')) ?> <?= $e($hm($open['entry_at'])) ?></p>
            <button type="button" class="btn btn-danger btn-lg w-100 js-attendance-out"
                    data-url="<?= $e(Url::to('/attendance/out')) ?>">
                <i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i><?= $e($t('attendance.clock_out')) ?>
            </button>
        <?php elseif ($projects === []): ?>
            <p class="text-muted mb-0"><?= $e($t('attendance.no_projects')) ?></p>
        <?php else: ?>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('attendance.project')) ?></label>
                <select class="form-select form-select-lg js-attendance-project">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>"><?= $e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-success btn-lg w-100 js-attendance-in"
                    data-url="<?= $e(Url::to('/attendance/in')) ?>">
                <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i><?= $e($t('attendance.clock_in')) ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<h2 class="app-section-title"><?= $e($t('attendance.recent')) ?></h2>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('attendance.project')) ?></th>
                    <th><?= $e($t('attendance.entry')) ?></th>
                    <th><?= $e($t('attendance.exit')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($recent === []): ?>
                <tr><td colspan="3" class="text-center text-muted py-3"><?= $e($t('attendance.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($recent as $a): ?>
                <tr>
                    <td class="fw-medium"><?= $e($a['project_name']) ?></td>
                    <td class="mono tnum"><?= $e($hm($a['entry_at'])) ?><?php if ($a['entry_lat'] !== null): ?> <i class="bi bi-geo-alt-fill text-muted" title="GPS" aria-hidden="true"></i><?php endif; ?></td>
                    <td><?= $a['exit_at'] !== null
                        ? '<span class="mono tnum">' . $e($hm($a['exit_at'])) . '</span>'
                        : '<span class="badge rounded-pill app-status app-status-success">' . $e($t('attendance.on_site')) . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
