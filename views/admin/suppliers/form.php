<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $supplier null = create, row = edit */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $supplier !== null;
$pageTitle = $isEdit ? $t('admin.suppliers.edit') : $t('admin.suppliers.new');
$value     = static fn (string $key): string => (string) ($supplier[$key] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? (string) $supplier['name'] : $t('admin.suppliers.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/suppliers'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.suppliers.title'), '/admin/suppliers'],
    [$isEdit ? (string) $supplier['name'] : $t('admin.suppliers.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/suppliers')) ?>"
              data-redirect="<?= $e(Url::to('/admin/suppliers')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $supplier['id'] : '') ?>">

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.suppliers.name')) ?></label>
                <input type="text" class="form-control" name="name" value="<?= $e($value('name')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.suppliers.vat')) ?></label>
                <input type="text" class="form-control" name="vat_or_tax_id" value="<?= $e($value('vat_or_tax_id')) ?>">
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.suppliers.email')) ?></label>
                    <input type="email" class="form-control" name="email" value="<?= $e($value('email')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.suppliers.phone')) ?></label>
                    <input type="text" class="form-control" name="phone" value="<?= $e($value('phone')) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.suppliers.address')) ?></label>
                <input type="text" class="form-control" name="address" value="<?= $e($value('address')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.suppliers.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"><?= $e($value('notes')) ?></textarea>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/suppliers')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
