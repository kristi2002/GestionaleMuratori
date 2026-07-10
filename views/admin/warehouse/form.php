<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $item null = create, row = edit */
/** @var array<int,string> $units */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $item !== null;
$pageTitle = $isEdit ? $t('admin.warehouse.edit') : $t('admin.warehouse.new');
$value     = static fn (string $key): string => (string) ($item[$key] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? (string) $item['name'] : $t('admin.warehouse.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/warehouse'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.warehouse.title'), '/admin/warehouse'],
    [$isEdit ? (string) $item['name'] : $t('admin.warehouse.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/warehouse')) ?>"
              data-redirect="<?= $e(Url::to('/admin/warehouse')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $item['id'] : '') ?>">

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.warehouse.name')) ?></label>
                <input type="text" class="form-control" name="name" value="<?= $e($value('name')) ?>" required>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.warehouse.sku')) ?></label>
                    <input type="text" class="form-control" name="sku" value="<?= $e($value('sku')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.warehouse.unit')) ?></label>
                    <select class="form-select" name="unit">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $e($u) ?>"<?= $value('unit') === $u ? ' selected' : '' ?>><?= $e(Lang::label('units', $u)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.warehouse.reorder_level')) ?></label>
                <input type="number" step="0.001" min="0" class="form-control" name="reorder_level" value="<?= $e($isEdit ? $value('reorder_level') : '0') ?>">
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
