<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $orders  Each row includes subtotal (lines only, VAT excluded). */
/** @var array<int,array<string,mixed>> $suppliers */
/** @var array{search:string,status:string,supplier_id:int} $filters */
/** @var array<int,string> $statuses */
/** @var array<string,int> $statusCounts */
/** @var int $totalCount */
/** @var array<string,string> $summary */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

// Keep the active search/supplier filter while switching the status pill.
$pillHref = static function (string $status) use ($filters): string {
    $q = array_filter([
        'q'           => $filters['search'] ?? '',
        'supplier_id' => ($filters['supplier_id'] ?? 0) ?: null,
        'status'      => $status,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/purchase-orders' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/purchase-orders/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.purchase_orders.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.purchase_orders.title'),
    'subtitle' => $t('admin.purchase_orders.subtitle'),
    'actions'  => $actions,
], null);

$sm = static fn (string $k): string => (string) ($summary[$k] ?? '0');
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100">
            <div class="card-body">
                <i class="bi bi-receipt-cutoff gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($sm('total_count')) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.purchase_orders.kpi_total')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 is-primary">
            <div class="card-body">
                <i class="bi bi-hourglass-split gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($sm('open_count')) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.purchase_orders.kpi_open')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 warn">
            <div class="card-body">
                <i class="bi bi-truck gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($sm('awaiting_count')) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.purchase_orders.kpi_awaiting')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 ok">
            <div class="card-body">
                <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($money($sm('total_value'))) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.purchase_orders.kpi_value')) ?></div>
                <div class="gm-kpi-sub"><?= $e($t('admin.purchase_orders.kpi_value_sub')) ?></div>
            </div>
        </div>
    </div>
</div>

<?php
$statusDots = ['draft' => 'secondary', 'sent' => 'info', 'confirmed' => 'primary', 'partially_received' => 'warning', 'received' => 'success', 'cancelled' => 'danger'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['status'] ?? '') === '',
    'count'  => $totalCount,
]];
foreach ($statuses as $st) {
    $pills[] = [
        'label'  => Lang::label('po_status', $st),
        'href'   => $pillHref($st),
        'active' => ($filters['status'] ?? '') === $st,
        'count'  => $statusCounts[$st] ?? 0,
        'dot'    => $statusDots[$st] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);
?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-3">
            <?php if (($filters['status'] ?? '') !== ''): ?>
                <input type="hidden" name="status" value="<?= $e($filters['status']) ?>">
            <?php endif; ?>
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>"
                   placeholder="<?= $e($t('admin.purchase_orders.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="supplier_id" aria-label="<?= $e($t('admin.suppliers.title')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.suppliers.title')) ?></option>
                <?php foreach ($suppliers as $s): ?>
                    <option value="<?= $e((string) $s['id']) ?>" <?= $filters['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                        <?= $e($s['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['search'] !== '' || $filters['supplier_id'] > 0,
                'href'   => $filters['status'] !== '' ? $pillHref($filters['status']) : '/admin/purchase-orders',
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
                    <th><?= $e($t('admin.purchase_orders.number')) ?></th>
                    <th><?= $e($t('admin.purchase_orders.field_title')) ?></th>
                    <th><?= $e($t('admin.suppliers.title')) ?></th>
                    <th><?= $e($t('admin.purchase_orders.date')) ?></th>
                    <th><?= $e($t('admin.purchase_orders.expected_date')) ?></th>
                    <th class="text-end"><?= $e($t('admin.purchase_orders.total')) ?></th>
                    <th><?= $e($t('admin.purchase_orders.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($orders === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-receipt-cutoff" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.purchase_orders.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/purchase-orders')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($orders as $o): ?>
                <?php
                $total     = (float) $o['subtotal'] * (1 + (float) $o['vat_rate'] / 100);
                $receivable = in_array($o['status'], ['sent', 'confirmed', 'partially_received'], true);
                $editable   = in_array($o['status'], ['draft', 'sent', 'confirmed', 'cancelled'], true);
                ?>
                <tr>
                    <td class="fw-semibold"><?= $e($o['number']) ?></td>
                    <td>
                        <?= $e($o['title']) ?>
                        <?php if ($o['project_name']): ?>
                            <div class="small text-muted"><?= $e($o['project_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($o['supplier_name']) ?></td>
                    <td><?= $e($date($o['order_date'])) ?></td>
                    <td><?= $e($date($o['expected_date'])) ?></td>
                    <td class="text-end"><?= $e($money($total)) ?></td>
                    <td>
                        <?= View::render('partials/status_badge', ['group' => 'po_status', 'value' => (string) $o['status']], null) ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($receivable): ?>
                            <a class="btn btn-sm btn-outline-success" href="<?= $e(Url::to('/admin/purchase-orders/' . $o['id'] . '/receive')) ?>">
                                <i class="bi bi-box-arrow-in-down" aria-hidden="true"></i> <?= $e($t('admin.purchase_orders.receive_action')) ?>
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/purchase-orders/' . $o['id'] . '/pdf')) ?>">
                            <i class="bi bi-printer" aria-hidden="true"></i> PDF
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/purchase-orders/' . $o['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <?php if ($editable): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                    data-url="<?= $e(Url::to('/admin/purchase-orders/' . $o['id'] . '/delete')) ?>"
                                    data-confirm="<?= $e($t('admin.purchase_orders.delete_confirm')) ?>">
                                <?= $e($t('common.delete')) ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
