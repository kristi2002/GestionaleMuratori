<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $order Row from PurchaseOrderModel::find(). */
/** @var array<int,array<string,mixed>> $lines Lines with a qty_received column. */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
// Trim trailing zeros only when there is a decimal point — otherwise an integer
// value like 800.0 (stringified as "800") would be eaten down to "8".
$num  = static fn ($v): string => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v;
$date = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

$stockLines    = array_values(array_filter($lines, static fn ($l): bool => $l['item_id'] !== null));
$nonStockLines = array_values(array_filter($lines, static fn ($l): bool => $l['item_id'] === null));
?>
<?= View::render('partials/page_head', [
    'title'    => $t('admin.purchase_orders.receive_title'),
    'subtitle' => $order['number'] . ' — ' . $order['supplier_name'],
    'actions'  => View::render('partials/back_button', ['href' => '/admin/purchase-orders', 'label' => $t('admin.purchase_orders.back_to_list')], null),
], null) ?>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.purchase_orders.title'), '/admin/purchase-orders'],
    [(string) $order['number'], null],
    [$t('admin.purchase_orders.receive_title'), null],
]], null) ?>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3"><?= $e($t('admin.purchase_orders.delivery_location')) ?></dt>
            <dd class="col-sm-9"><?= $e($order['location_name']) ?></dd>
            <dt class="col-sm-3"><?= $e($t('admin.purchase_orders.expected_date')) ?></dt>
            <dd class="col-sm-9"><?= $e($date($order['expected_date'])) ?></dd>
            <dt class="col-sm-3"><?= $e($t('admin.purchase_orders.status')) ?></dt>
            <dd class="col-sm-9"><?= View::render('partials/status_badge', ['group' => 'po_status', 'value' => (string) $order['status']], null) ?></dd>
        </dl>
    </div>
</div>

<div class="app-banner is-info mb-3" role="note">
    <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
    <span><?= $e($t('admin.purchase_orders.receive_hint')) ?></span>
</div>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/purchase-orders/' . $order['id'] . '/receive')) ?>"
              data-redirect="<?= $e(Url::to('/admin/purchase-orders')) ?>">

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th><?= $e($t('admin.purchase_orders.line_item')) ?></th>
                            <th class="text-end"><?= $e($t('admin.purchase_orders.ordered')) ?></th>
                            <th class="text-end"><?= $e($t('admin.purchase_orders.received_so_far')) ?></th>
                            <th class="text-end"><?= $e($t('admin.purchase_orders.remaining')) ?></th>
                            <th style="width: 160px;"><?= $e($t('admin.purchase_orders.receive_now')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($stockLines === []): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.purchase_orders.no_stock_lines')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($stockLines as $l): ?>
                        <?php
                        $ordered   = (float) $l['qty'];
                        $received  = (float) $l['qty_received'];
                        $remaining = max(0.0, $ordered - $received);
                        $done      = $received + 1e-9 >= $ordered;
                        ?>
                        <tr class="<?= $done ? 'table-success' : '' ?>">
                            <td>
                                <span class="fw-semibold"><?= $e($l['description']) ?></span>
                                <?php if (!empty($l['item_name']) && $l['item_name'] !== $l['description']): ?>
                                    <div class="small text-muted"><?= $e($l['item_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= $e($num($l['qty'])) ?> <span class="text-muted small"><?= $e($l['unit'] ?? $l['item_unit'] ?? '') ?></span></td>
                            <td class="text-end"><?= $e($num($received)) ?></td>
                            <td class="text-end"><?= $e($num($remaining)) ?></td>
                            <td>
                                <?php if ($done): ?>
                                    <span class="badge text-bg-success"><i class="bi bi-check-lg" aria-hidden="true"></i> <?= $e($t('admin.purchase_orders.line_complete')) ?></span>
                                <?php else: ?>
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                           name="received[<?= $e((string) $l['id']) ?>]" value="<?= $e($num($remaining)) ?>">
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($nonStockLines !== []): ?>
                <p class="text-muted small mb-2"><?= $e($t('admin.purchase_orders.non_stock_note')) ?></p>
                <ul class="text-muted small">
                    <?php foreach ($nonStockLines as $l): ?>
                        <li><?= $e($l['description']) ?> — <?= $e($num($l['qty'])) ?> <?= $e($l['unit'] ?? '') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success" <?= $stockLines === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> <?= $e($t('admin.purchase_orders.receive_submit')) ?>
                </button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/purchase-orders')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
