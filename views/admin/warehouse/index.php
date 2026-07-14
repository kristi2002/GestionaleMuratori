<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $items */
/** @var string $search */
/** @var array<int,string> $units */
/** @var array{total:int,inventory_value:float} $summary */
/** @var int $lowStockCount */
/** @var int $movementsToday */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$num = static fn (string $v): string => rtrim(rtrim($v, '0'), '.');
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/warehouse/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.warehouse.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.warehouse.title'),
    'subtitle' => $t('admin.warehouse.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-primary h-100">
            <i class="bi bi-box-seam gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $summary['total']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.warehouse.kpi_total')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($money($summary['inventory_value'])) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.warehouse.kpi_value')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi <?= $lowStockCount > 0 ? 'alert' : 'ok' ?> h-100">
            <i class="bi bi-exclamation-triangle gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $lowStockCount) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.warehouse.kpi_low_stock')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-info h-100">
            <i class="bi bi-arrow-left-right gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $movementsToday) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.warehouse.kpi_movements_today')) ?></div>
        </div>
    </div>
</div>

<?php if ($lowStockCount > 0): ?>
    <div class="app-banner is-warn mb-3">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span><?= $e(sprintf($t('admin.warehouse.low_stock_banner'), $lowStockCount)) ?></span>
    </div>
<?php endif; ?>

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
                    <th></th>
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
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.warehouse.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $it): ?>
                <?php $low = (float) $it['reorder_level'] > 0 && (float) $it['qty_in_stock'] <= (float) $it['reorder_level']; ?>
                <tr class="<?= $low ? 'sev-bad' : '' ?>">
                    <td class="text-center text-muted"><i class="bi bi-box-seam" aria-hidden="true"></i></td>
                    <td><a href="<?= $e(Url::to('/admin/warehouse/' . $it['id'])) ?>"><?= $e($it['name']) ?></a></td>
                    <td><?= $e($it['sku']) ?></td>
                    <td>
                        <?= $e($num((string) $it['qty_in_stock'])) ?> <?= $e(Lang::label('units', $it['unit'])) ?>
                        <?php if ($low): ?>
                            <span class="badge text-bg-warning ms-1"><?= $e($t('admin.warehouse.low_stock')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($num((string) $it['reorder_level'])) ?></td>
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
