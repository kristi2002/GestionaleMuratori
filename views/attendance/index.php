<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $open  the caller's open attendance, if clocked in */
/** @var array<int,array<string,mixed>> $projects  projects the caller may clock into */
/** @var array<int,array<string,mixed>> $recent */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$hm = static fn (?string $dt): string => $dt ? substr((string) $dt, 0, 16) : '';
?>
<h1 class="h4 mb-1"><?= $e($t('attendance.title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('attendance.subtitle')) ?></p>

<div class="alert alert-danger d-none js-attendance-error" role="alert"></div>
<div class="alert alert-info d-none js-attendance-geo" role="status"><?= $e($t('attendance.locating')) ?></div>

<div class="card mb-3">
    <div class="card-body">
        <?php if ($open !== null): ?>
            <p class="mb-1">
                <span class="badge text-bg-success"><?= $e($t('attendance.on_site')) ?></span>
            </p>
            <p class="mb-1"><strong><?= $e($open['project_name']) ?></strong></p>
            <p class="small text-muted mb-3"><?= $e($t('attendance.since')) ?> <?= $e($hm($open['entry_at'])) ?></p>
            <button type="button" class="btn btn-danger btn-lg w-100 js-attendance-out"
                    data-url="<?= $e(Url::to('/attendance/out')) ?>">
                <?= $e($t('attendance.clock_out')) ?>
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
                <?= $e($t('attendance.clock_in')) ?>
            </button>
        <?php endif; ?>
    </div>
</div>

<h2 class="h6 text-muted mb-2"><?= $e($t('attendance.recent')) ?></h2>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
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
                    <td><?= $e($a['project_name']) ?></td>
                    <td><?= $e($hm($a['entry_at'])) ?><?php if ($a['entry_lat'] !== null): ?> <span title="GPS">📍</span><?php endif; ?></td>
                    <td><?= $a['exit_at'] !== null ? $e($hm($a['exit_at'])) : '<span class="badge text-bg-success">' . $e($t('attendance.on_site')) . '</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
