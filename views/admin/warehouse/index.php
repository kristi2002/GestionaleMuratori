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
    <a class="btn btn-success" href="<?= $e(Url::to('/admin/warehouse/create')) ?>">
        <?= $e($t('admin.warehouse.new')) ?>
    </a>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-2">
            <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $search !== '',
                'href'   => '/admin/warehouse',
                'inline' => true,
            ], null) ?>
        </form>
    </div>
</div>

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
                        <a class="btn btn-sm btn-outline-primary" href="<?= $e(Url::to('/admin/warehouse/' . $it['id'])) ?>#trasferisci"><?= $e($t('admin.warehouse.transfer.action')) ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse/' . $it['id'])) ?>#registro"><?= $e($t('admin.warehouse.ledger')) ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse/' . $it['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
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

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
