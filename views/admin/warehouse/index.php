<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $items */
/** @var string $search */
/** @var array<int,string> $units */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.warehouse.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.warehouse.subtitle')) ?></p>
    </div>
    <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#item-modal" data-target-modal="#item-modal">
        <?= $e($t('admin.warehouse.new')) ?>
    </button>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-4">
        <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>">
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
                    <th><?= $e($t('admin.warehouse.name')) ?></th>
                    <th><?= $e($t('admin.warehouse.sku')) ?></th>
                    <th><?= $e($t('admin.warehouse.qty_in_stock')) ?></th>
                    <th><?= $e($t('admin.warehouse.reorder_level')) ?></th>
                    <th><?= $e($t('admin.warehouse.is_active')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.warehouse.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $it): ?>
                <?php $low = (float) $it['qty_in_stock'] <= (float) $it['reorder_level']; ?>
                <tr>
                    <td><a href="<?= $e(Url::to('/admin/warehouse/' . $it['id'])) ?>"><?= $e($it['name']) ?></a></td>
                    <td><?= $e($it['sku']) ?></td>
                    <td>
                        <?= $e(rtrim(rtrim((string) $it['qty_in_stock'], '0'), '.')) ?> <?= $e(Lang::label('units', $it['unit'])) ?>
                        <?php if ($low): ?>
                            <span class="badge text-bg-warning ms-1"><?= $e($t('admin.warehouse.low_stock')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e(rtrim(rtrim((string) $it['reorder_level'], '0'), '.')) ?></td>
                    <td>
                        <?php if ((int) $it['is_active'] === 1): ?>
                            <span class="badge text-bg-success"><?= $e($t('admin.warehouse.is_active')) ?></span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><?= $e($t('admin.warehouse.deactivate')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse/' . $it['id'])) ?>"><?= $e($t('admin.warehouse.ledger')) ?></a>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit"
                                data-bs-toggle="modal" data-bs-target="#item-modal" data-target-modal="#item-modal"
                                data-record='<?= $e(json_encode($it, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger js-toggle-active"
                                data-url="<?= $e(Url::to('/admin/warehouse/' . $it['id'] . '/toggle')) ?>">
                            <?= $e((int) $it['is_active'] === 1 ? $t('admin.warehouse.deactivate') : $t('admin.warehouse.activate')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="item-modal" tabindex="-1" data-title-create="<?= $e($t('admin.warehouse.new')) ?>" data-title-edit="<?= $e($t('admin.warehouse.edit')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/warehouse')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.warehouse.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.name')) ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.warehouse.sku')) ?></label>
                            <input type="text" class="form-control" name="sku">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.warehouse.unit')) ?></label>
                            <select class="form-select" name="unit">
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= $e($u) ?>"><?= $e(Lang::label('units', $u)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.warehouse.reorder_level')) ?></label>
                        <input type="number" step="0.001" min="0" class="form-control" name="reorder_level" value="0">
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
