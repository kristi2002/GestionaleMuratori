<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $clients */
/** @var string $search */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.clients.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.clients.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/clients/export' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) ?>">
            <i class="bi bi-download" aria-hidden="true"></i> <?= $e($t('common.export_csv')) ?>
        </a>
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/clients/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.clients.new')) ?>
        </a>
    </div>
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
                'href'   => '/admin/clients',
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
                    <th><?= $e($t('admin.clients.name')) ?></th>
                    <th><?= $e($t('admin.clients.vat')) ?></th>
                    <th><?= $e($t('admin.clients.email')) ?></th>
                    <th><?= $e($t('admin.clients.phone')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($clients === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.clients.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($clients as $c): ?>
                <tr>
                    <td><?= $e($c['name']) ?></td>
                    <td><?= $e($c['vat_or_tax_id']) ?></td>
                    <td><?= $e($c['email']) ?></td>
                    <td><?= $e($c['phone']) ?></td>
                    <td class="text-end app-row-actions">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/clients/' . $c['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/clients/' . $c['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.clients.delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
