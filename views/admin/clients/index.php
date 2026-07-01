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
    <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#client-modal" data-target-modal="#client-modal">
        <?= $e($t('admin.clients.new')) ?>
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
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit"
                                data-bs-toggle="modal" data-bs-target="#client-modal" data-target-modal="#client-modal"
                                data-record='<?= $e(json_encode($c, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
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

<div class="modal fade" id="client-modal" tabindex="-1" data-title-create="<?= $e($t('admin.clients.new')) ?>" data-title-edit="<?= $e($t('admin.clients.edit')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/clients')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.clients.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.name')) ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.vat')) ?></label>
                        <input type="text" class="form-control" name="vat_or_tax_id">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.clients.email')) ?></label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.clients.phone')) ?></label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.address')) ?></label>
                        <input type="text" class="form-control" name="address">
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.clients.notes')) ?></label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
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
