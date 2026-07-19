<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $intervention */
/** @var array<int,array<string,mixed>> $materials */
/** @var array{before:array,during:array,after:array} $photosByType */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$status      = $intervention['status'];
$isOpen      = in_array($status, ['pending', 'in_progress', 'on_hold'], true);
$canComplete = $status === 'in_progress';

$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('worker.start')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('worker.hold')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('worker.resume')]],
];

$schedule = null;
if ($intervention['scheduled_date']) {
    $schedule = $intervention['scheduled_date']
        . ($intervention['scheduled_start_time'] ? ' ' . substr((string) $intervention['scheduled_start_time'], 0, 5) : '');
}

echo View::render('partials/page_head', [
    'title'    => (string) $intervention['title'],
    'subtitle' => $intervention['project_name'] . ' — ' . $intervention['client_name'],
    'actions'  => View::render('partials/back_button', ['href' => '/worker'], null),
], null);
?>

<div class="alert alert-warning d-none js-offline-queue-banner" role="status"></div>

<div class="card app-record-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <h2 class="h6 mb-0"><?= $e($t('worker.status')) ?></h2>
            <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $status], null) ?>
        </div>
        <dl class="app-dl mb-0">
            <?php if ($intervention['description']): ?>
                <div class="app-dl-row">
                    <dt><?= $e($t('worker.description')) ?></dt>
                    <dd><?= $e($intervention['description']) ?></dd>
                </div>
            <?php endif; ?>
            <div class="app-dl-row">
                <dt><?= $e($t('worker.project')) ?></dt>
                <dd><?= $e($intervention['project_name']) ?></dd>
            </div>
            <div class="app-dl-row">
                <dt><?= $e($t('worker.client')) ?></dt>
                <dd><?= $e($intervention['client_name']) ?></dd>
            </div>
            <?php if ($schedule !== null): ?>
                <div class="app-dl-row">
                    <dt><?= $e($t('worker.scheduled_time')) ?></dt>
                    <dd><i class="bi bi-calendar-event me-1" aria-hidden="true"></i><?= $e($schedule) ?></dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if (($nextActions[$status] ?? []) !== []): ?>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <?php foreach ($nextActions[$status] as $action): ?>
                    <button type="button" class="btn btn-success js-intervention-status"
                            data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/status')) ?>"
                            data-to-status="<?= $e($action['to']) ?>">
                        <?= $e($action['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$fmtDur = static function (int $s): string {
    $h = intdiv($s, 3600); $m = intdiv($s % 3600, 60);
    return $h > 0 ? $h . 'h ' . $m . 'm' : $m . 'm';
};
$timerHere       = $timerHere ?? null;
$timerOtherTitle = $timerOtherTitle ?? null;
$timeTotal       = (int) ($timeTotal ?? 0);
?>
<div class="card app-record-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 mb-1"><?= $e($t('worker.timer')) ?></h2>
                <div class="small text-muted">
                    <?= $e($t('worker.time_total')) ?>: <span class="fw-semibold"><?= $e($fmtDur($timeTotal)) ?></span>
                    <?php if ($timerHere !== null): ?>
                        · <span class="text-success"><i class="bi bi-record-circle" aria-hidden="true"></i>
                            <span class="js-timer-elapsed" data-elapsed="<?= (int) ($timerElapsed ?? 0) ?>">—</span></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <?php if ($timerHere !== null): ?>
                    <button type="button" class="btn btn-danger js-timer-toggle"
                            data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/timer/stop')) ?>">
                        <i class="bi bi-stop-fill" aria-hidden="true"></i> <?= $e($t('worker.timer_stop')) ?>
                    </button>
                <?php else: ?>
                    <button type="button" class="btn btn-success js-timer-toggle"
                            data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/timer/start')) ?>">
                        <i class="bi bi-play-fill" aria-hidden="true"></i> <?= $e($t('worker.timer_start')) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($timerOtherTitle !== null): ?>
            <div class="alert alert-warning mt-2 mb-0 small"><?= $e(sprintf($t('worker.timer_other'), $timerOtherTitle)) ?></div>
        <?php endif; ?>
    </div>
</div>

<?php
$tasks     = $tasks ?? [];
$taskDone  = 0;
foreach ($tasks as $task) { $taskDone += (int) $task['is_done'] === 1 ? 1 : 0; }
$taskTotal = count($tasks);
?>
<?php if ($taskTotal > 0): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= $e($t('worker.checklist')) ?></span>
        <span class="badge bg-secondary-subtle text-secondary-emphasis js-task-progress"
              data-done="<?= (int) $taskDone ?>" data-total="<?= (int) $taskTotal ?>"><?= (int) $taskDone ?>/<?= (int) $taskTotal ?></span>
    </div>
    <div class="card-body">
        <div class="js-task-list">
            <?php foreach ($tasks as $task): $done = (int) $task['is_done'] === 1; ?>
                <div class="form-check mb-2">
                    <input class="form-check-input js-task-toggle" type="checkbox" id="task-<?= $e((string) $task['id']) ?>"
                           data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/tasks/' . $task['id'] . '/toggle')) ?>"
                           <?= $done ? 'checked' : '' ?>>
                    <label class="form-check-label js-task-label <?= $done ? 'text-decoration-line-through text-muted' : '' ?>" for="task-<?= $e((string) $task['id']) ?>">
                        <?= $e($task['label']) ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($materials === [] && !$isOpen): ?>
    <!-- no materials, nothing to show -->
<?php else: ?>
<div class="card mb-3">
    <div class="card-header"><?= $e($t('worker.materials')) ?></div>
    <div class="card-body">
        <?php if ($materials === []): ?>
            <p class="text-muted mb-0"><?= $e($t('worker.no_materials')) ?></p>
        <?php else: ?>
            <?php foreach ($materials as $m): ?>
                <div class="row align-items-center mb-2">
                    <div class="col-6"><?= $e($m['item_name']) ?> <span class="text-muted small">(<?= $e(Lang::label('units', $m['unit'])) ?>)</span></div>
                    <div class="col-3 small text-muted"><?= $e($t('worker.qty_planned')) ?>: <?= $e(rtrim(rtrim((string) $m['qty_planned'], '0'), '.')) ?></div>
                    <div class="col-3">
                        <?php if ($canComplete): ?>
                            <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                   form="complete-form" name="qty_used[<?= $e((string) $m['id']) ?>]"
                                   value="<?= $e((string) $m['qty_planned']) ?>" required>
                        <?php else: ?>
                            <span class="small"><?= $e($t('worker.qty_used')) ?>: <?= $e($m['qty_used'] !== null ? rtrim(rtrim((string) $m['qty_used'], '0'), '.') : '—') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php foreach (['before', 'during', 'after'] as $type): ?>
    <div class="card mb-3">
        <div class="card-header"><?= $e($t('worker.photos')) ?> — <?= $e(Lang::label('photo_types', $type)) ?></div>
        <div class="card-body">
            <?php if ($photosByType[$type] === []): ?>
                <p class="text-muted small"><?= $e($t('worker.no_photos')) ?></p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($photosByType[$type] as $photo): ?>
                        <a href="<?= $e(Url::to('/worker/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                            <img src="<?= $e(Url::to('/worker/photos/' . $photo['id'] . '/thumb')) ?>" alt=""
                                 class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($isOpen): ?>
                <form class="js-photo-upload-form" data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/photos')) ?>" data-type="<?= $e($type) ?>">
                    <div class="alert alert-danger d-none js-photo-error" role="alert"></div>
                    <div class="input-group">
                        <input type="file" class="form-control" accept="image/*" capture="environment" required>
                        <button type="submit" class="btn btn-outline-success"><?= $e($t('worker.upload_photo')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($canComplete): ?>
<div class="card mb-3">
    <div class="card-header"><?= $e($t('worker.signature')) ?></div>
    <div class="card-body">
        <?php if ($intervention['client_signature_path']): ?>
            <p class="small text-success mb-2"><?= $e($t('worker.signature_saved')) ?></p>
            <img src="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/signature')) ?>" alt="" class="border rounded mb-3" style="max-width:100%;">
        <?php endif; ?>
        <div class="alert alert-danger d-none js-signature-error" role="alert"></div>
        <canvas id="signature-pad" class="border rounded w-100 bg-white" height="160" style="touch-action:none;"></canvas>
        <div class="mt-2 d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary js-signature-clear"><?= $e($t('worker.signature_clear')) ?></button>
            <button type="button" class="btn btn-sm btn-success js-signature-save"
                    data-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/signature')) ?>"
                    data-empty-message="<?= $e($t('worker.signature_empty')) ?>">
                <?= $e($t('worker.signature_save')) ?>
            </button>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><?= $e($t('worker.complete')) ?></div>
    <div class="card-body">
        <form id="complete-form" class="js-crud-form" data-base-url="<?= $e(Url::to('/worker/interventions/' . $intervention['id'] . '/complete')) ?>" data-confirm="<?= $e($t('worker.complete_confirm')) ?>">
            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('worker.completion_notes')) ?></label>
                <textarea class="form-control" name="completion_notes" rows="2"></textarea>
            </div>
            <button type="submit" class="btn btn-success w-100"><?= $e($t('worker.complete')) ?></button>
        </form>
    </div>
</div>
<?php endif; ?>
