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
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.daily_logs.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.daily_logs.subtitle')) ?></p>
    </div>
    <?php if ($projectId > 0): ?>
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/daily-logs/create?project_id=' . $projectId)) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.daily_logs.new')) ?>
        </a>
    <?php endif; ?>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-6">
        <select class="form-select" name="project_id" onchange="this.form.submit()">
            <?php foreach ($projects as $p): ?>
                <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.daily_logs.date')) ?></th>
                    <th><?= $e($t('admin.daily_logs.weather')) ?></th>
                    <th><?= $e($t('admin.daily_logs.workers')) ?></th>
                    <th><?= $e($t('admin.daily_logs.state')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($logs === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.daily_logs.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="mono tnum fw-bold"><?= $e($log['log_date']) ?></td>
                    <td>
                        <?= $e($log['weather_text'] ?? '—') ?>
                        <?php if ($log['temp_min'] !== null || $log['temp_max'] !== null): ?>
                            <span class="text-muted small">(<?= $e((string) $log['temp_min']) ?>° / <?= $e((string) $log['temp_max']) ?>°)</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($log['workers_present'] !== null ? (string) $log['workers_present'] : '—') ?></td>
                    <td>
                        <?php if ((int) $log['is_closed'] === 1): ?>
                            <span class="badge text-bg-secondary"><?= $e($t('admin.daily_logs.closed_badge')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-success"><?= $e($t('admin.daily_logs.open_badge')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/daily-logs/' . $log['id'])) ?>"><?= $e($t('admin.daily_logs.open')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
