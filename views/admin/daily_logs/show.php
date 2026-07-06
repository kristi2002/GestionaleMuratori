<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $log */
/** @var array<int,array<string,mixed>> $equipment  active catalog */
/** @var array<int,int> $equipmentIds  ids attached to this log */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$closed  = (int) $log['is_closed'] === 1;
$backUrl = Url::to('/admin/daily-logs?project_id=' . $log['project_id']);
?>
<a href="<?= $e($backUrl) ?>" class="d-inline-block mb-3 small">&larr; <?= $e($t('admin.daily_logs.back')) ?></a>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h5 mb-1"><?= $e($log['project_name']) ?></h1>
                <p class="small text-muted mb-0"><?= $e($t('admin.daily_logs.date')) ?>: <span class="mono tnum"><?= $e($log['log_date']) ?></span></p>
            </div>
            <?php if ($closed): ?>
                <span class="badge text-bg-secondary"><?= $e($t('admin.daily_logs.closed_badge')) ?></span>
            <?php else: ?>
                <span class="badge text-bg-success"><?= $e($t('admin.daily_logs.open_badge')) ?></span>
            <?php endif; ?>
        </div>
        <p class="small text-muted mb-0 mt-2">
            <?= $e($t('admin.daily_logs.created_by')) ?>: <?= $e($log['created_by_name']) ?>
            <?php if ($closed): ?>
                — <?= $e($t('admin.daily_logs.closed_by')) ?>: <?= $e($log['closed_by_name'] ?? '') ?> (<?= $e(substr((string) $log['closed_at'], 0, 16)) ?>)
            <?php endif; ?>
        </p>
    </div>
</div>

<?php if ($closed): ?>
    <div class="alert alert-secondary"><?= $e($t('admin.daily_logs.closed_notice')) ?></div>
    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0">
                <dt class="col-sm-3"><?= $e($t('admin.daily_logs.weather')) ?></dt>
                <dd class="col-sm-9"><?= $e($log['weather_text'] ?? '—') ?>
                    <?php if ($log['temp_min'] !== null || $log['temp_max'] !== null): ?>
                        (<?= $e((string) $log['temp_min']) ?>° / <?= $e((string) $log['temp_max']) ?>°)
                    <?php endif; ?>
                </dd>
                <dt class="col-sm-3"><?= $e($t('admin.daily_logs.workers')) ?></dt>
                <dd class="col-sm-9"><?= $e($log['workers_present'] !== null ? (string) $log['workers_present'] : '—') ?></dd>
                <dt class="col-sm-3"><?= $e($t('admin.daily_logs.work_done')) ?></dt>
                <dd class="col-sm-9"><?= nl2br($e($log['work_done'] ?? '—')) ?></dd>
                <dt class="col-sm-3"><?= $e($t('admin.daily_logs.notes')) ?></dt>
                <dd class="col-sm-9"><?= nl2br($e($log['notes'] ?? '—')) ?></dd>
            </dl>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-header bg-white"><?= $e($t('admin.daily_logs.details')) ?></div>
        <div class="card-body">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/daily-logs')) ?>">
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <input type="hidden" name="id" value="<?= $e((string) $log['id']) ?>">
                <div class="mb-3">
                    <label class="form-label"><?= $e($t('admin.daily_logs.weather')) ?></label>
                    <input type="text" class="form-control" name="weather_text" value="<?= $e($log['weather_text']) ?>">
                </div>
                <div class="row">
                    <div class="col-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.daily_logs.temp_min')) ?></label>
                        <input type="number" step="0.1" class="form-control" name="temp_min" value="<?= $e($log['temp_min']) ?>">
                    </div>
                    <div class="col-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.daily_logs.temp_max')) ?></label>
                        <input type="number" step="0.1" class="form-control" name="temp_max" value="<?= $e($log['temp_max']) ?>">
                    </div>
                    <div class="col-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.daily_logs.workers')) ?></label>
                        <input type="number" min="0" step="1" class="form-control" name="workers_present" value="<?= $e($log['workers_present']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $e($t('admin.daily_logs.work_done')) ?></label>
                    <textarea class="form-control" name="work_done" rows="3"><?= $e($log['work_done']) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $e($t('admin.daily_logs.notes')) ?></label>
                    <textarea class="form-control" name="notes" rows="2"><?= $e($log['notes']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header bg-white"><?= $e($t('admin.daily_logs.equipment')) ?></div>
    <div class="card-body">
        <?php if ($closed): ?>
            <?php $attached = array_filter($equipment, static fn ($eq) => in_array((int) $eq['id'], $equipmentIds, true)); ?>
            <?php if ($attached === []): ?>
                <p class="text-muted mb-0"><?= $e($t('admin.daily_logs.no_equipment')) ?></p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($attached as $eq): ?><li><?= $e($eq['name']) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <form class="js-crud-form mb-3" data-base-url="<?= $e(Url::to('/admin/daily-logs/' . $log['id'] . '/equipment')) ?>">
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <?php if ($equipment === []): ?>
                    <p class="text-muted"><?= $e($t('admin.daily_logs.no_equipment_catalog')) ?></p>
                <?php endif; ?>
                <?php foreach ($equipment as $eq): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="equipment_ids[]" value="<?= $e((string) $eq['id']) ?>"
                               id="eq-<?= $e((string) $eq['id']) ?>" <?= in_array((int) $eq['id'], $equipmentIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="eq-<?= $e((string) $eq['id']) ?>"><?= $e($eq['name']) ?></label>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="btn btn-sm btn-success mt-2"><?= $e($t('admin.daily_logs.save_equipment')) ?></button>
            </form>
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/equipment')) ?>">
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <label class="form-label small"><?= $e($t('admin.daily_logs.add_equipment')) ?></label>
                <div class="input-group">
                    <input type="text" class="form-control" name="name" placeholder="<?= $e($t('admin.daily_logs.equipment_name')) ?>">
                    <button type="submit" class="btn btn-outline-secondary"><?= $e($t('common.create')) ?></button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!$closed): ?>
    <div class="card border-warning">
        <div class="card-body">
            <p class="mb-2 small text-muted"><?= $e($t('admin.daily_logs.close_help')) ?></p>
            <button type="button" class="btn btn-warning js-crud-delete"
                    data-url="<?= $e(Url::to('/admin/daily-logs/' . $log['id'] . '/close')) ?>"
                    data-confirm="<?= $e($t('admin.daily_logs.close_confirm')) ?>">
                <?= $e($t('admin.daily_logs.close')) ?>
            </button>
        </div>
    </div>
<?php endif; ?>
