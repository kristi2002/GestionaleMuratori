<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $log */
/** @var array<int,array<string,mixed>> $equipment  active catalog */
/** @var array<int,int> $equipmentIds  ids attached to this log */
/** @var array<int,string> $weatherCodes */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$closed   = (int) $log['is_closed'] === 1;
$attached = array_values(array_filter($equipment, static fn ($eq) => in_array((int) $eq['id'], $equipmentIds, true)));

// Real recorded facts, rendered oldest→newest as a work-log feed.
$createdTime = substr((string) ($log['created_at'] ?? ''), 11, 5);
$closedTime  = substr((string) ($log['closed_at'] ?? ''), 11, 5);

$statusBadge = $closed
    ? '<span class="badge text-bg-secondary">' . $e($t('admin.daily_logs.closed_badge')) . '</span>'
    : '<span class="badge text-bg-success">' . $e($t('admin.daily_logs.open_badge')) . '</span>';

echo View::render('partials/page_head', [
    'title'    => (string) $log['project_name'],
    'subtitle' => $t('admin.daily_logs.date') . ': ' . (string) $log['log_date'],
    'actions'  => $statusBadge,
], null);
?>

<div class="app-cols">
    <div>
        <h2 class="app-section-title"><?= $e($t('admin.daily_logs.title')) ?></h2>

        <div class="mb-4">
            <!-- Work performed: the core activity of the day -->
            <div class="app-timeline-item">
                <span class="app-timeline-icon"><i class="bi bi-hammer" aria-hidden="true"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <strong><?= $e($t('admin.daily_logs.work_done')) ?></strong>
                        <span class="app-timeline-type"><?= $e($log['log_date']) ?></span>
                    </div>
                    <p class="mb-2 mt-1">
                        <?php if (($log['work_done'] ?? '') !== ''): ?>
                            <?= nl2br($e($log['work_done'])) ?>
                        <?php else: ?>
                            <span class="text-muted"><?= $e($t('admin.daily_logs.no_work')) ?></span>
                        <?php endif; ?>
                    </p>
                    <div class="small text-muted">
                        <i class="bi bi-person" aria-hidden="true"></i> <?= $e($log['created_by_name']) ?>
                    </div>
                </div>
            </div>

            <!-- Compiled: who opened the day and when -->
            <div class="app-timeline-item">
                <span class="app-timeline-icon is-project"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <strong><?= $e($t('admin.daily_logs.timeline_compiled')) ?></strong>
                        <?php if ($createdTime !== ''): ?>
                            <span class="app-timeline-type"><?= $e($createdTime) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted mt-1">
                        <i class="bi bi-person" aria-hidden="true"></i> <?= $e($log['created_by_name']) ?>
                    </div>
                </div>
            </div>

            <?php if ($closed): ?>
                <!-- Closure: legal lock event -->
                <div class="app-timeline-item">
                    <span class="app-timeline-icon is-project"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <strong><?= $e($t('admin.daily_logs.timeline_closed')) ?></strong>
                            <?php if ($closedTime !== ''): ?>
                                <span class="app-timeline-type"><?= $e($closedTime) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-person" aria-hidden="true"></i> <?= $e($log['closed_by_name'] ?? '') ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($closed): ?>
            <div class="alert alert-secondary"><?= $e($t('admin.daily_logs.closed_notice')) ?></div>
        <?php else: ?>
            <div class="card mb-3">
                <div class="card-header"><?= $e($t('admin.daily_logs.details')) ?></div>
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
            <div class="card-header"><?= $e($t('admin.daily_logs.equipment')) ?></div>
            <div class="card-body">
                <?php if ($closed): ?>
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
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('admin.daily_logs.summary')) ?></h2>
            <dl class="app-dl">
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.daily_logs.state')) ?></dt>
                    <dd><?= $statusBadge ?></dd>
                </div>
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.daily_logs.weather')) ?></dt>
                    <dd>
                        <?= $e($log['weather_text'] ?? '—') ?>
                        <?php if ($log['temp_min'] !== null || $log['temp_max'] !== null): ?>
                            <span class="text-muted">(<?= $e((string) $log['temp_min']) ?>° / <?= $e((string) $log['temp_max']) ?>°)</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.daily_logs.workers')) ?></dt>
                    <dd><?= $e($log['workers_present'] !== null ? (string) $log['workers_present'] : '—') ?></dd>
                </div>
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.daily_logs.created_by')) ?></dt>
                    <dd><?= $e($log['created_by_name']) ?></dd>
                </div>
                <?php if ($closed): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('admin.daily_logs.closed_by')) ?></dt>
                        <dd><?= $e($log['closed_by_name'] ?? '') ?><?= $closedTime !== '' ? ' (' . $e(substr((string) $log['closed_at'], 0, 16)) . ')' : '' ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($attached !== []): ?>
            <div class="app-rail-card">
                <h2 class="app-rail-title"><?= $e($t('admin.daily_logs.equipment')) ?></h2>
                <ul class="mb-0 ps-3">
                    <?php foreach ($attached as $eq): ?><li><?= $e($eq['name']) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (($log['notes'] ?? '') !== ''): ?>
            <div class="app-rail-card">
                <h2 class="app-rail-title"><?= $e($t('admin.daily_logs.notes')) ?></h2>
                <p class="small mb-0"><?= nl2br($e($log['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
