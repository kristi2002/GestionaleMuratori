<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $quotes Each row includes subtotal (lines only, VAT excluded). */
/** @var array<int,array<string,mixed>> $clients */
/** @var array{search:string,status:string,client_id:int} $filters */
/** @var array<int,string> $statuses */
/** @var array<string,int> $statusCounts */
/** @var int $totalCount */
/** @var array<string,string> $summary */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

// Keep the active search/client filter while switching the status pill.
$pillHref = static function (string $status) use ($filters): string {
    $q = array_filter([
        'q'         => $filters['search'] ?? '',
        'client_id' => ($filters['client_id'] ?? 0) ?: null,
        'status'    => $status,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/quotes' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/quotes/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.quotes.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.quotes.title'),
    'subtitle' => $t('admin.quotes.subtitle'),
    'actions'  => $actions,
], null);

// KPI cards — all values come from QuoteModel::summary() (real data, whole table).
$sm         = static fn (string $k): string => (string) ($summary[$k] ?? '0');
$totalQ     = (int) $sm('total_count');
$acceptedQ  = (int) $sm('accepted_count');
$acceptRate = $totalQ > 0 ? (int) round($acceptedQ / $totalQ * 100) : 0;
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100">
            <div class="card-body">
                <i class="bi bi-file-earmark-text gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e((string) $totalQ) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.quotes.kpi_total')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 is-primary">
            <div class="card-body">
                <i class="bi bi-graph-up-arrow gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($acceptRate . '%') ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.quotes.kpi_acceptance')) ?></div>
                <div class="gm-kpi-sub"><?= $e($acceptedQ . ' / ' . $totalQ) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 ok">
            <div class="card-body">
                <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($money($sm('total_value'))) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.quotes.kpi_value')) ?></div>
                <div class="gm-kpi-sub"><?= $e($t('admin.quotes.kpi_value_sub')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 warn">
            <div class="card-body">
                <i class="bi bi-hourglass-split gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e($sm('pending_count')) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('admin.quotes.kpi_pending')) ?></div>
                <div class="gm-kpi-sub"><?= $e(Lang::label('quote_status', 'sent')) ?></div>
            </div>
        </div>
    </div>
</div>

<?php
// Status pill filters (Tutti + one per quote_status, each with its real count).
$statusDots = ['draft' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'rejected' => 'danger', 'expired' => 'warning'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['status'] ?? '') === '',
    'count'  => $totalCount,
]];
foreach ($statuses as $st) {
    $pills[] = [
        'label'  => Lang::label('quote_status', $st),
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
                   placeholder="<?= $e($t('admin.quotes.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="client_id" aria-label="<?= $e($t('admin.clients.title')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.clients.title')) ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $e((string) $c['id']) ?>" <?= $filters['client_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= $e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['search'] !== '' || $filters['client_id'] > 0,
                'href'   => $filters['status'] !== '' ? $pillHref($filters['status']) : '/admin/quotes',
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
                    <th><?= $e($t('admin.quotes.number')) ?></th>
                    <th><?= $e($t('admin.quotes.field_title')) ?></th>
                    <th><?= $e($t('admin.projects.client')) ?></th>
                    <th><?= $e($t('admin.quotes.date')) ?></th>
                    <th><?= $e($t('admin.quotes.valid_until')) ?></th>
                    <th class="text-end"><?= $e($t('admin.quotes.total')) ?></th>
                    <th><?= $e($t('admin.quotes.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($quotes === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.quotes.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/quotes')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($quotes as $q): ?>
                <?php $total = (float) $q['subtotal'] * (1 + (float) $q['vat_rate'] / 100); ?>
                <tr>
                    <td class="fw-semibold"><?= $e($q['number']) ?></td>
                    <td>
                        <?= $e($q['title']) ?>
                        <?php if ($q['project_name']): ?>
                            <div class="small text-muted"><?= $e($q['project_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($q['client_name']) ?></td>
                    <td><?= $e($date($q['quote_date'])) ?></td>
                    <td><?= $e($date($q['valid_until'])) ?></td>
                    <td class="text-end"><?= $e($money($total)) ?></td>
                    <td>
                        <?= View::render('partials/status_badge', ['group' => 'quote_status', 'value' => (string) $q['status']], null) ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($q['status'] === 'accepted' && $q['project_name']): ?>
                            <button type="button" class="btn btn-sm btn-outline-success js-post-action"
                                    data-url="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/invoice')) ?>"
                                    data-confirm="<?= $e($t('admin.quotes.to_invoice_confirm')) ?>"
                                    data-ok-label="<?= $e($t('admin.quotes.to_invoice')) ?>">
                                <i class="bi bi-receipt" aria-hidden="true"></i> <?= $e($t('admin.quotes.to_invoice')) ?>
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/pdf')) ?>">
                            <i class="bi bi-printer" aria-hidden="true"></i> PDF
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.quotes.delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
