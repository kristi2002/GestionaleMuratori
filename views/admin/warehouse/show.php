<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $item */
/** @var array<int,array<string,mixed>> $movements */
/** @var array<int,array<string,mixed>> $balances */
/** @var array<int,array<string,mixed>> $locations */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$num = static fn (string $v): string => rtrim(rtrim($v, '0'), '.');
?>
<a href="<?= $e(Url::to('/admin/warehouse')) ?>" class="d-inline-block mb-3 small">&larr; <?= $e($t('admin.warehouse.back_to_list')) ?></a>

<div class="row g-3">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body">
                <h1 class="h5 mb-3"><?= $e($item['name']) ?></h1>
                <dl class="row mb-0 small">
                    <dt class="col-6"><?= $e($t('admin.warehouse.sku')) ?></dt>
                    <dd class="col-6"><?= $e($item['sku']) ?></dd>
                    <dt class="col-6"><?= $e($t('admin.warehouse.unit')) ?></dt>
                    <dd class="col-6"><?= $e(Lang::label('units', $item['unit'])) ?></dd>
                    <dt class="col-6"><?= $e($t('admin.warehouse.qty_in_stock')) ?></dt>
                    <dd class="col-6 fw-bold js-qty-in-stock"><?= $e((string) $item['qty_in_stock']) ?></dd>
                    <dt class="col-6"><?= $e($t('admin.warehouse.reorder_level')) ?></dt>
                    <dd class="col-6"><?= $e((string) $item['reorder_level']) ?></dd>
                </dl>
                <div class="alert alert-info d-none mt-2 mb-2 js-reconcile-result" role="alert"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary w-100 js-reconcile-btn"
                        data-url="<?= $e(Url::to('/admin/warehouse/' . $item['id'] . '/reconcile')) ?>">
                    <?= $e($t('admin.warehouse.reconcile')) ?>
                </button>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h2 class="h6 mb-3"><?= $e($t('admin.warehouse.add_movement')) ?></h2>
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/warehouse/' . $item['id'] . '/movement')) ?>" data-no-id="1">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.movement_type')) ?></label>
                        <select class="form-select" name="type">
                            <option value="in"><?= $e(Lang::label('movement_types', 'in')) ?></option>
                            <option value="adjustment"><?= $e(Lang::label('movement_types', 'adjustment')) ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.movement_qty')) ?></label>
                        <input type="number" step="0.001" class="form-control" name="qty" required>
                        <div class="form-text"><?= $e($t('admin.warehouse.movement_qty_help')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.movement_note')) ?></label>
                        <input type="text" class="form-control" name="note">
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?= $e($t('admin.warehouse.add_movement')) ?></button>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white"><?= $e($t('admin.warehouse.balances_by_location')) ?></div>
            <ul class="list-group list-group-flush">
                <?php if ($balances === []): ?>
                    <li class="list-group-item small text-muted"><?= $e($t('admin.warehouse.no_balances')) ?></li>
                <?php endif; ?>
                <?php foreach ($balances as $b): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small">
                            <?= $e($b['location_name']) ?>
                            <span class="badge text-bg-light border ms-1"><?= $e(Lang::label('stock_location_kinds', $b['location_kind'])) ?></span>
                        </span>
                        <span class="fw-bold"><?= $e($num((string) $b['qty'])) ?> <?= $e(Lang::label('units', $item['unit'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card mt-3 app-anchor" id="trasferisci">
            <div class="card-body">
                <h2 class="h6 mb-3"><?= $e($t('admin.warehouse.transfer.title')) ?></h2>
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/warehouse/' . $item['id'] . '/transfer')) ?>">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.transfer.from_location')) ?></label>
                        <select class="form-select" name="from_location_id" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $e((string) $loc['id']) ?>"<?= (int) $loc['id'] === 1 ? ' selected' : '' ?>><?= $e($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.transfer.to_location')) ?></label>
                        <select class="form-select" name="to_location_id" required>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= $e((string) $loc['id']) ?>"><?= $e($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.transfer.qty')) ?></label>
                        <input type="number" step="0.001" min="0" class="form-control" name="qty" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.warehouse.transfer.note')) ?></label>
                        <input type="text" class="form-control" name="note">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><?= $e($t('admin.warehouse.transfer.submit')) ?></button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card app-anchor" id="registro">
            <div class="card-header bg-white"><?= $e($t('admin.warehouse.ledger')) ?></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= $e($t('admin.warehouse.movement_date')) ?></th>
                            <th><?= $e($t('admin.warehouse.movement_type')) ?></th>
                            <th class="text-end"><?= $e($t('admin.warehouse.movement_qty')) ?></th>
                            <th><?= $e($t('admin.warehouse.movement_user')) ?></th>
                            <th><?= $e($t('admin.warehouse.movement_note')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($movements === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.warehouse.movement_empty')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td class="small"><?= $e($m['created_at']) ?></td>
                            <td><span class="badge text-bg-light border"><?= $e(Lang::label('movement_types', $m['type'])) ?></span></td>
                            <td class="text-end"><?= $e((string) $m['qty']) ?></td>
                            <td class="small"><?= $e($m['user_name']) ?></td>
                            <td class="small text-muted"><?= $e($m['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
