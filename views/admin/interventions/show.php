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

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/interventions/' . $intervention['id'] . '/edit')) . '">'
    . '<i class="bi bi-pencil" aria-hidden="true"></i> ' . $e($t('common.edit')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin/interventions'], null);

echo View::render('partials/page_head', [
    'title'    => (string) $intervention['title'],
    'subtitle' => $intervention['project_name'] . ' — ' . $intervention['client_name'],
    'actions'  => $actions,
], null);
?>

<div class="app-cols">
    <div>
        <?php if ($intervention['description']): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="app-rail-title"><?= $e($t('admin.interventions.description')) ?></h2>
                    <p class="mb-0"><?= nl2br($e($intervention['description'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $tasks     = $tasks ?? [];
        $taskDone  = 0;
        foreach ($tasks as $task) { $taskDone += (int) $task['is_done'] === 1 ? 1 : 0; }
        $taskTotal = count($tasks);
        ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= $e($t('admin.interventions.checklist')) ?></span>
                <span class="badge bg-secondary-subtle text-secondary-emphasis js-task-progress"
                      data-done="<?= (int) $taskDone ?>" data-total="<?= (int) $taskTotal ?>"><?= (int) $taskDone ?>/<?= (int) $taskTotal ?></span>
            </div>
            <div class="card-body">
                <?php if ($tasks !== []): ?>
                    <div class="js-task-list mb-3">
                        <?php foreach ($tasks as $task): $done = (int) $task['is_done'] === 1; ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input class="form-check-input mt-0 js-task-toggle" type="checkbox"
                                       data-url="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/tasks/' . $task['id'] . '/toggle')) ?>"
                                       <?= $done ? 'checked' : '' ?>>
                                <span class="flex-grow-1 js-task-label <?= $done ? 'text-decoration-line-through text-muted' : '' ?>"><?= $e($task['label']) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-danger js-task-delete"
                                        data-url="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/tasks/' . $task['id'] . '/delete')) ?>"
                                        data-confirm="<?= $e($t('admin.interventions.checklist_delete_confirm')) ?>"
                                        aria-label="<?= $e($t('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small"><?= $e($t('admin.interventions.checklist_empty')) ?></p>
                <?php endif; ?>
                <form class="js-task-add-form d-flex gap-2" data-url="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/tasks')) ?>">
                    <input type="text" class="form-control form-control-sm" name="label" maxlength="255"
                           placeholder="<?= $e($t('admin.interventions.checklist_add_placeholder')) ?>" required>
                    <button type="submit" class="btn btn-sm btn-success"><?= $e($t('admin.interventions.checklist_add')) ?></button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><?= $e($t('admin.interventions.materials')) ?></div>
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
                    <div class="card-header"><?= $e($t('worker.photos')) ?> — <?= $e(Lang::label('photo_types', $type)) ?></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($photosByType[$type] as $photo): ?>
                                <div class="text-center">
                                    <a href="<?= $e(Url::to('/admin/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                                        <img src="<?= $e(Url::to('/admin/photos/' . $photo['id'] . '/thumb')) ?>" alt=""
                                             class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                                    </a>
                                    <?php if (($photo['captured_at'] ?? null) !== null): ?>
                                        <div class="text-muted" style="font-size:.7rem;"><?= $e(substr((string) $photo['captured_at'], 0, 16)) ?></div>
                                    <?php endif; ?>
                                    <?php if (($photo['lat'] ?? null) !== null && ($photo['lng'] ?? null) !== null): ?>
                                        <a class="small" style="font-size:.7rem;" target="_blank" rel="noopener"
                                           href="https://www.openstreetmap.org/?mlat=<?= $e((string) $photo['lat']) ?>&mlon=<?= $e((string) $photo['lng']) ?>">
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i> GPS
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($intervention['client_signature_path']): ?>
            <div class="card mb-3">
                <div class="card-header"><?= $e($t('worker.signature')) ?></div>
                <div class="card-body">
                    <img src="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/signature')) ?>" alt=""
                         class="border rounded" style="max-width:100%;max-height:200px;">
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><?= $e($t('admin.interventions.history')) ?></div>
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
                                    <td><?php if ($h['from_status'] !== null): ?><?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $h['from_status']], null) ?><?php else: ?>—<?php endif; ?></td>
                                    <td><?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $h['to_status']], null) ?></td>
                                    <td><?= $e($h['changed_by_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('admin.interventions.details')) ?></h2>

            <div class="mb-3">
                <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => $status], null) ?>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <?php if (($intervention['worker_name'] ?? null) !== null): ?>
                    <span class="app-avatars">
                        <span class="app-avatar"><?= $e(View::initials((string) $intervention['worker_name'])) ?></span>
                    </span>
                    <span><?= $e($intervention['worker_name']) ?></span>
                <?php else: ?>
                    <span class="app-avatars">
                        <span class="app-avatar is-more"><i class="bi bi-person" aria-hidden="true"></i></span>
                    </span>
                    <span class="text-muted"><?= $e($t('admin.interventions.unassigned')) ?></span>
                <?php endif; ?>
            </div>

            <dl class="app-dl">
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.interventions.scheduled_date')) ?></dt>
                    <dd>
                        <?= $e($intervention['scheduled_date'] ?? '—') ?>
                        <?= $intervention['scheduled_start_time'] ? ' ' . $e(substr((string) $intervention['scheduled_start_time'], 0, 5)) : '' ?>
                    </dd>
                </div>
                <?php if ($intervention['started_at']): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('admin.interventions.started_at')) ?></dt>
                        <dd><?= $e($intervention['started_at']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($intervention['completed_at']): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('admin.interventions.completed_at')) ?></dt>
                        <dd><?= $e($intervention['completed_at']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($intervention['completion_notes']): ?>
                <div class="mt-3">
                    <div class="small text-muted mb-1"><?= $e($t('worker.completion_notes')) ?></div>
                    <div class="small"><?= nl2br($e($intervention['completion_notes'])) ?></div>
                </div>
            <?php endif; ?>

            <?php if (($nextActions[$status] ?? []) !== []): ?>
                <div class="mt-3 pt-3 border-top d-grid gap-2">
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
</div>
