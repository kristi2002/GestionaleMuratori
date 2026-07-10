<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $intervention null = create, row = edit */
/** @var array<int,array<string,mixed>> $materials Existing planned materials (edit only) */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $warehouseItems */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$isEdit    = $intervention !== null;
$pageTitle = $isEdit ? $t('admin.interventions.edit') : $t('admin.interventions.new');
$value     = static fn (string $key): string => (string) ($intervention[$key] ?? '');

// One material editor row (used for the first row and the clone template on create).
$materialRow = static function () use ($warehouseItems, $e, $t): string {
    ob_start(); ?>
    <div class="row g-2 mb-2 js-material-row">
        <div class="col-7">
            <select class="form-select" name="item_id[]">
                <option value="">—</option>
                <?php foreach ($warehouseItems as $wi): ?>
                    <option value="<?= $e((string) $wi['id']) ?>">
                        <?= $e($wi['name']) ?> (<?= $e(rtrim(rtrim((string) $wi['qty_in_stock'], '0'), '.')) ?> <?= $e(Lang::label('units', $wi['unit'])) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-3">
            <input type="number" step="0.001" min="0" class="form-control" name="qty_planned[]"
                   placeholder="<?= $e($t('admin.interventions.qty_planned')) ?>">
        </div>
        <div class="col-2">
            <button type="button" class="btn btn-outline-secondary w-100 js-material-remove" aria-label="<?= $e($t('admin.interventions.remove_material')) ?>">&times;</button>
        </div>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? (string) $intervention['title'] : $t('admin.interventions.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/interventions', 'label' => $t('admin.interventions.back_to_list')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.interventions.title'), '/admin/interventions'],
    [$isEdit ? (string) $intervention['title'] : $t('admin.interventions.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/interventions')) ?>"
              data-redirect="<?= $e(Url::to('/admin/interventions')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $intervention['id'] : '') ?>">

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.project')) ?></label>
                    <select class="form-select" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>" <?= (int) $value('project_id') === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.worker')) ?></label>
                    <select class="form-select" name="assigned_worker_id">
                        <option value=""><?= $e($t('admin.interventions.unassigned')) ?></option>
                        <?php foreach ($workers as $w): ?>
                            <option value="<?= $e((string) $w['id']) ?>" <?= (int) $value('assigned_worker_id') === (int) $w['id'] ? 'selected' : '' ?>>
                                <?= $e($w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.interventions.field_title')) ?></label>
                <input type="text" class="form-control" name="title" value="<?= $e($value('title')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.interventions.description')) ?></label>
                <textarea class="form-control" name="description" rows="2"><?= $e($value('description')) ?></textarea>
            </div>
            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.scheduled_date')) ?></label>
                    <input type="date" class="form-control" name="scheduled_date" value="<?= $e($value('scheduled_date')) ?>">
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.scheduled_time')) ?></label>
                    <input type="time" class="form-control" name="scheduled_start_time"
                           value="<?= $e($value('scheduled_start_time') !== '' ? substr($value('scheduled_start_time'), 0, 5) : '') ?>">
                </div>
            </div>

            <hr>
            <?php if (!$isEdit): ?>
                <?php // Materials are only set at creation (they reserve stock). ?>
                <div class="js-materials-section">
                    <label class="form-label mb-1"><?= $e($t('admin.interventions.materials')) ?></label>
                    <p class="small text-muted mb-2"><?= $e($t('admin.interventions.materials_hint')) ?></p>
                    <div class="js-materials-rows">
                        <?= $materialRow() ?>
                    </div>
                    <template class="js-material-template"><?= $materialRow() ?></template>
                    <button type="button" class="btn btn-sm btn-outline-success js-material-add">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.interventions.add_material')) ?>
                    </button>
                </div>
            <?php else: ?>
                <label class="form-label mb-1"><?= $e($t('admin.interventions.materials')) ?></label>
                <p class="small text-muted mb-2"><?= $e($t('admin.interventions.materials_locked')) ?></p>
                <?php if ($materials === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('admin.interventions.no_materials')) ?></p>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($materials as $m): ?>
                            <li class="d-flex align-items-center gap-2 py-1">
                                <i class="bi bi-box-seam text-muted" aria-hidden="true"></i>
                                <?= $e($m['item_name']) ?>
                                <span class="text-muted">— <?= $e($qty($m['qty_planned'])) ?> <?= $e(Lang::label('units', $m['unit'])) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>

            <div class="alert alert-danger d-none js-crud-error mt-3" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top mt-3">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
