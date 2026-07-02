<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $interventions */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $warehouseItems */
/** @var array{project_id:int,worker_id:int,status:string} $filters */
/** @var array<int,string> $statuses */
/** @var string $range */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$rangeLink = static function (string $value) use ($filters): string {
    $query = array_filter([
        'project_id' => $filters['project_id'] ?: null,
        'worker_id'  => $filters['worker_id'] ?: null,
        'status'     => $filters['status'] ?: null,
        'range'      => $value ?: null,
    ]);
    return Url::to('/admin/interventions') . ($query !== [] ? '?' . http_build_query($query) : '');
};

/** Status transitions an admin can trigger from the list (completed is worker-only, Phase 5). */
$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.start')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('admin.interventions.hold')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.resume')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
];
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.interventions.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.interventions.subtitle')) ?></p>
    </div>
    <button type="button" class="btn btn-success js-crud-new js-intervention-new" data-bs-toggle="modal" data-bs-target="#intervention-modal" data-target-modal="#intervention-modal">
        <?= $e($t('admin.interventions.new')) ?>
    </button>
</div>

<div class="btn-group mb-3" role="group">
    <a class="btn btn-sm <?= $range === 'today' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('today')) ?>"><?= $e($t('admin.interventions.range_today')) ?></a>
    <a class="btn btn-sm <?= $range === 'week' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('week')) ?>"><?= $e($t('admin.interventions.range_week')) ?></a>
    <a class="btn btn-sm <?= $range === '' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('')) ?>"><?= $e($t('admin.interventions.range_all')) ?></a>
</div>

<form method="get" class="row g-2 mb-3">
    <input type="hidden" name="range" value="<?= $e($range) ?>">
    <div class="col-6 col-sm-3">
        <select class="form-select" name="project_id">
            <option value=""><?= $e($t('admin.interventions.project')) ?> — <?= $e($t('common.all')) ?></option>
            <?php foreach ($projects as $p): ?>
                <option value="<?= $e((string) $p['id']) ?>" <?= ((int) $filters['project_id'] === (int) $p['id']) ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="worker_id">
            <option value=""><?= $e($t('admin.interventions.worker')) ?> — <?= $e($t('common.all')) ?></option>
            <?php foreach ($workers as $w): ?>
                <option value="<?= $e((string) $w['id']) ?>" <?= ((int) $filters['worker_id'] === (int) $w['id']) ? 'selected' : '' ?>><?= $e($w['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="status">
            <option value=""><?= $e($t('admin.interventions.status')) ?> — <?= $e($t('common.all')) ?></option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $e(Lang::label('intervention_status', $s)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary"><?= $e($t('common.search')) ?></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.interventions.field_title')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th><?= $e($t('admin.interventions.worker')) ?></th>
                    <th><?= $e($t('admin.interventions.scheduled_date')) ?></th>
                    <th><?= $e($t('admin.interventions.materials')) ?></th>
                    <th><?= $e($t('admin.interventions.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($interventions === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.interventions.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($interventions as $iv): ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/interventions/' . $iv['id'])) ?>" class="text-decoration-none"><?= $e($iv['title']) ?></a>
                        <div class="small text-muted"><?= $e($iv['client_name']) ?></div>
                    </td>
                    <td><?= $e($iv['project_name']) ?></td>
                    <td><?= $e($iv['worker_name'] ?? $t('admin.interventions.unassigned')) ?></td>
                    <td class="small">
                        <?= $e($iv['scheduled_date']) ?>
                        <?= $iv['scheduled_start_time'] ? ' ' . $e(substr((string) $iv['scheduled_start_time'], 0, 5)) : '' ?>
                    </td>
                    <td class="small">
                        <?php if ($iv['materials'] === []): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?php foreach ($iv['materials'] as $m): ?>
                                <div><?= $e($m['item_name']) ?>: <?= $e(rtrim(rtrim((string) $m['qty_planned'], '0'), '.')) ?> <?= $e(Lang::label('units', $m['unit'])) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit js-intervention-edit"
                                data-bs-toggle="modal" data-bs-target="#intervention-modal" data-target-modal="#intervention-modal"
                                data-record='<?= $e(json_encode($iv, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
                        <?php foreach ($nextActions[$iv['status']] ?? [] as $action): ?>
                            <button type="button" class="btn btn-sm <?= $action['to'] === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-intervention-status"
                                    data-url="<?= $e(Url::to('/admin/interventions/' . $iv['id'] . '/status')) ?>"
                                    data-to-status="<?= $e($action['to']) ?>"
                                    <?= $action['to'] === 'cancelled' ? 'data-confirm="' . $e($t('admin.interventions.cancel_confirm')) . '"' : '' ?>>
                                <?= $e($action['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="intervention-modal" tabindex="-1" data-title-create="<?= $e($t('admin.interventions.new')) ?>" data-title-edit="<?= $e($t('admin.interventions.edit')) ?>">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/interventions')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.interventions.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="row">
                        <div class="col-12 col-sm-6 mb-3 js-intervention-project-field">
                            <label class="form-label"><?= $e($t('admin.interventions.project')) ?></label>
                            <select class="form-select" name="project_id">
                                <option value="">—</option>
                                <?php foreach ($projects as $p): ?>
                                    <option value="<?= $e((string) $p['id']) ?>"><?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.interventions.worker')) ?></label>
                            <select class="form-select" name="assigned_worker_id">
                                <option value=""><?= $e($t('admin.interventions.unassigned')) ?></option>
                                <?php foreach ($workers as $w): ?>
                                    <option value="<?= $e((string) $w['id']) ?>"><?= $e($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.interventions.field_title')) ?></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.interventions.description')) ?></label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.interventions.scheduled_date')) ?></label>
                            <input type="date" class="form-control" name="scheduled_date">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.interventions.scheduled_time')) ?></label>
                            <input type="time" class="form-control" name="scheduled_start_time">
                        </div>
                    </div>

                    <div class="js-materials-section">
                        <hr>
                        <label class="form-label"><?= $e($t('admin.interventions.materials')) ?></label>
                        <div class="js-materials-rows">
                            <div class="row g-2 mb-2 js-material-row">
                                <div class="col-7">
                                    <select class="form-select" name="item_id[]">
                                        <option value="">—</option>
                                        <?php foreach ($warehouseItems as $wi): ?>
                                            <option value="<?= $e((string) $wi['id']) ?>"><?= $e($wi['name']) ?> (<?= $e(rtrim(rtrim((string) $wi['qty_in_stock'], '0'), '.')) ?> <?= $e(Lang::label('units', $wi['unit'])) ?> <?= $e($t('admin.warehouse.qty_in_stock')) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-3">
                                    <input type="number" step="0.001" min="0" class="form-control" name="qty_planned[]" placeholder="<?= $e($t('admin.interventions.qty_planned')) ?>">
                                </div>
                                <div class="col-2">
                                    <button type="button" class="btn btn-outline-secondary w-100 js-material-remove">&times;</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-success js-material-add"><?= $e($t('admin.interventions.add_material')) ?></button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $e($t('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
