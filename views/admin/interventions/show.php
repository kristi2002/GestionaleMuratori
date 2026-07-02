<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $intervention */
/** @var array<int,array<string,mixed>> $materials */
/** @var array<int,array<string,mixed>> $history */
/** @var array{before:array,during:array,after:array} $photosByType */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$qty = static fn ($v): string => $v !== null ? rtrim(rtrim((string) $v, '0'), '.') : '—';

$status = (string) $intervention['status'];

$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.start')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('admin.interventions.hold')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.resume')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
];
?>
<a href="<?= $e(Url::to('/admin/interventions')) ?>" class="d-inline-block mb-3 small">&larr; <?= $e($t('admin.interventions.back_to_list')) ?></a>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h1 class="h5 mb-1"><?= $e($intervention['title']) ?></h1>
                <p class="small text-muted mb-0">
                    <?= $e($intervention['project_name']) ?> — <?= $e($intervention['client_name']) ?>
                </p>
            </div>
            <span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $status)) ?></span>
        </div>

        <dl class="row small mt-3 mb-0">
            <dt class="col-sm-3"><?= $e($t('admin.interventions.worker')) ?></dt>
            <dd class="col-sm-9"><?= $e($intervention['worker_name'] ?? $t('admin.interventions.unassigned')) ?></dd>

            <dt class="col-sm-3"><?= $e($t('admin.interventions.scheduled_date')) ?></dt>
            <dd class="col-sm-9">
                <?= $e($intervention['scheduled_date'] ?? '—') ?>
                <?= $intervention['scheduled_start_time'] ? ' ' . $e(substr((string) $intervention['scheduled_start_time'], 0, 5)) : '' ?>
            </dd>

            <?php if ($intervention['description']): ?>
                <dt class="col-sm-3"><?= $e($t('admin.interventions.description')) ?></dt>
                <dd class="col-sm-9"><?= $e($intervention['description']) ?></dd>
            <?php endif; ?>

            <?php if ($intervention['started_at']): ?>
                <dt class="col-sm-3"><?= $e($t('admin.interventions.started_at')) ?></dt>
                <dd class="col-sm-9"><?= $e($intervention['started_at']) ?></dd>
            <?php endif; ?>

            <?php if ($intervention['completed_at']): ?>
                <dt class="col-sm-3"><?= $e($t('admin.interventions.completed_at')) ?></dt>
                <dd class="col-sm-9"><?= $e($intervention['completed_at']) ?></dd>
            <?php endif; ?>

            <?php if ($intervention['completion_notes']): ?>
                <dt class="col-sm-3"><?= $e($t('worker.completion_notes')) ?></dt>
                <dd class="col-sm-9"><?= $e($intervention['completion_notes']) ?></dd>
            <?php endif; ?>
        </dl>

        <?php if (($nextActions[$status] ?? []) !== []): ?>
            <div class="mt-3 d-flex gap-2">
                <?php foreach ($nextActions[$status] as $action): ?>
                    <button type="button" class="btn btn-sm <?= $action['to'] === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-intervention-status"
                            data-url="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/status')) ?>"
                            data-to-status="<?= $e($action['to']) ?>"
                            <?= $action['to'] === 'cancelled' ? 'data-confirm="' . $e($t('admin.interventions.cancel_confirm')) . '"' : '' ?>>
                        <?= $e($action['label']) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white"><?= $e($t('admin.interventions.materials')) ?></div>
    <div class="card-body">
        <?php if ($materials === []): ?>
            <p class="text-muted small mb-0"><?= $e($t('worker.no_materials')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= $e($t('admin.interventions.item')) ?></th>
                            <th><?= $e($t('worker.qty_planned')) ?></th>
                            <th><?= $e($t('worker.qty_used')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($materials as $m): ?>
                        <tr>
                            <td><?= $e($m['item_name']) ?> <span class="text-muted small">(<?= $e(Lang::label('units', $m['unit'])) ?>)</span></td>
                            <td><?= $e($qty($m['qty_planned'])) ?></td>
                            <td><?= $e($qty($m['qty_used'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php foreach (['before', 'during', 'after'] as $type): ?>
    <?php if ($photosByType[$type] !== []): ?>
        <div class="card mb-3">
            <div class="card-header bg-white"><?= $e($t('worker.photos')) ?> — <?= $e(Lang::label('photo_types', $type)) ?></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($photosByType[$type] as $photo): ?>
                        <a href="<?= $e(Url::to('/admin/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                            <img src="<?= $e(Url::to('/admin/photos/' . $photo['id'] . '/thumb')) ?>" alt=""
                                 class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($intervention['client_signature_path']): ?>
    <div class="card mb-3">
        <div class="card-header bg-white"><?= $e($t('worker.signature')) ?></div>
        <div class="card-body">
            <img src="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/signature')) ?>" alt=""
                 class="border rounded" style="max-width:100%;max-height:200px;">
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header bg-white"><?= $e($t('admin.interventions.history')) ?></div>
    <div class="card-body">
        <?php if ($history === []): ?>
            <p class="text-muted small mb-0"><?= $e($t('admin.interventions.history_empty')) ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= $e($t('admin.interventions.history_when')) ?></th>
                            <th><?= $e($t('admin.interventions.history_from')) ?></th>
                            <th><?= $e($t('admin.interventions.history_to')) ?></th>
                            <th><?= $e($t('admin.interventions.history_by')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td class="small"><?= $e($h['changed_at']) ?></td>
                            <td><?= $h['from_status'] !== null ? $e(Lang::label('intervention_status', $h['from_status'])) : '—' ?></td>
                            <td><?= $e(Lang::label('intervention_status', $h['to_status'])) ?></td>
                            <td><?= $e($h['changed_by_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
