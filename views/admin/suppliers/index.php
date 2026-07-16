<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $suppliers */
/** @var array{total:int,active:int,with_orders:int} $stats */
/** @var string $search */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/suppliers/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.suppliers.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.suppliers.title'),
    'subtitle' => $t('admin.suppliers.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="app-kpi-grid mb-4">
    <div class="card gm-kpi is-info">
        <i class="bi bi-truck gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['total']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.suppliers.kpi_total')) ?></div>
    </div>
    <div class="card gm-kpi ok">
        <i class="bi bi-check2-circle gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['active']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.suppliers.kpi_active')) ?></div>
    </div>
    <div class="card gm-kpi is-primary">
        <i class="bi bi-receipt-cutoff gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['with_orders']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.suppliers.kpi_with_orders')) ?></div>
    </div>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-2">
            <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('admin.suppliers.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $search !== '',
                'href'   => '/admin/suppliers',
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
                    <th><?= $e($t('admin.suppliers.name')) ?></th>
                    <th><?= $e($t('admin.suppliers.vat')) ?></th>
                    <th><?= $e($t('admin.suppliers.email')) ?></th>
                    <th><?= $e($t('admin.suppliers.phone')) ?></th>
                    <th><?= $e($t('admin.suppliers.active')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($suppliers === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.suppliers.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($suppliers as $s): ?>
                <tr class="<?= ((int) $s['is_active']) === 1 ? '' : 'table-secondary text-muted' ?>">
                    <td>
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="bi bi-truck" aria-hidden="true"></i>
                            <span class="fw-semibold"><?= $e($s['name']) ?></span>
                        </span>
                    </td>
                    <td><?= $e($s['vat_or_tax_id']) ?></td>
                    <td><?= $e($s['email']) ?></td>
                    <td><?= $e($s['phone']) ?></td>
                    <td><?= ((int) $s['is_active']) === 1 ? $e($t('common.yes')) : $e($t('common.no')) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/suppliers/' . $s['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-warning js-toggle-active"
                                data-url="<?= $e(Url::to('/admin/suppliers/' . $s['id'] . '/toggle')) ?>">
                            <?= ((int) $s['is_active']) === 1 ? $e($t('admin.suppliers.deactivate')) : $e($t('admin.suppliers.activate')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
