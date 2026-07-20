<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $invoices */
/** @var array<int,array<string,mixed>> $projects */
/** @var array{search:string,status:string,project_id:int} $filters */
/** @var array<int,string> $statuses */
/** @var array<string,int> $statusCounts */
/** @var int $totalCount */
/** @var array<string,string> $summary */
/** @var int $overdueDays */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

// A row is "scaduta" (overdue) once an issued invoice is older than $overdueDays.
$overdueBefore = date('Y-m-d', strtotime('-' . (int) $overdueDays . ' days'));
$isOverdue = static function (array $inv) use ($overdueBefore): bool {
    return ($inv['status'] ?? '') === 'issued'
        && ($inv['issue_date'] ?? '') !== ''
        && (string) $inv['issue_date'] < $overdueBefore;
};

// Keep the active search/project filter while switching the status pill.
$pillHref = static function (string $status) use ($filters): string {
    $q = array_filter([
        'q'          => $filters['search'] ?? '',
        'project_id' => ($filters['project_id'] ?? 0) ?: null,
        'status'     => $status,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/invoices' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/invoices/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.invoices.new')) . '</a>'
    . '<button type="button" class="btn btn-outline-secondary" disabled title="' . $e($t('nav.coming_soon')) . '">'
    . '<i class="bi bi-lightning-charge" aria-hidden="true"></i> ' . $e($t('admin.invoices.einvoice_soon')) . '</button>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.invoices.title'),
    'subtitle' => $t('admin.invoices.subtitle'),
    'actions'  => $actions,
], null);

// KPI cards — all values come from ProjectInvoiceModel::summary() (real data).
$s = static fn (string $k): string => (string) ($summary[$k] ?? '0');
$countSub = static fn (string $k) => $s($k) . ' ' . $t('admin.invoices.kpi_count');
$kpis = [
    ['', 'bi-receipt', 'kpi_emesse_month', 'issued_month_total', 'issued_month_count'],
    ['ok', 'bi-check2-circle', 'kpi_incassate', 'paid_total', 'paid_count'],
    ['warn', 'bi-hourglass-split', 'kpi_da_incassare', 'outstanding_total', 'outstanding_count'],
    ['is-danger', 'bi-exclamation-triangle', 'kpi_scadute', 'overdue_total', 'overdue_count'],
];
?>
<div class="row g-3 mb-3">
    <?php foreach ($kpis as [$variant, $icon, $labelKey, $valKey, $countKey]): ?>
        <div class="col-6 col-lg-3">
            <div class="card gm-kpi h-100<?= $variant !== '' ? ' ' . $variant : '' ?>">
                <div class="card-body">
                    <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                    <div class="gm-kpi-val mt-2"><?= $e($money($s($valKey))) ?></div>
                    <div class="gm-kpi-lab"><?= $e($t('admin.invoices.' . $labelKey)) ?></div>
                    <div class="gm-kpi-sub"><?= $e($countSub($countKey)) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
// Status pill filters (Tutti + one per invoice_status, each with its real count).
$statusDots = ['draft' => 'secondary', 'issued' => 'info', 'paid' => 'success'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['status'] ?? '') === '',
    'count'  => $totalCount,
]];
foreach ($statuses as $st) {
    $pills[] = [
        'label'  => Lang::label('invoice_status', $st),
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
                   placeholder="<?= $e($t('admin.invoices.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.projects.title')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.projects.title')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['search'] !== '' || $filters['project_id'] > 0,
                'href'   => $filters['status'] !== '' ? $pillHref($filters['status']) : '/admin/invoices',
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
                    <th><?= $e($t('admin.projects.invoice_number')) ?></th>
                    <th><?= $e($t('admin.projects.invoice_date')) ?></th>
                    <th><?= $e($t('admin.projects.client')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.projects.invoice_amount')) ?></th>
                    <th><?= $e($t('admin.projects.invoice_status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.invoices.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/invoices')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <?php $overdue = $isOverdue($inv); ?>
                <tr<?= $overdue ? ' class="sev-bad"' : '' ?>>
                    <td class="fw-semibold"><?= $e($inv['number']) ?></td>
                    <td>
                        <?= $e($date($inv['issue_date'])) ?>
                        <?php if ($overdue): ?>
                            <span class="badge rounded-pill app-status app-status-danger ms-1"
                                  title="<?= $e(strtr($t('admin.invoices.overdue_note'), [':days' => (string) $overdueDays])) ?>">
                                <?= $e($t('admin.invoices.kpi_scadute')) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($inv['client_name']) ?></td>
                    <td><?= $e($inv['project_name']) ?></td>
                    <td class="text-end"><?= $e($money($inv['amount'])) ?></td>
                    <td>
                        <?= View::render('partials/status_badge', ['group' => 'invoice_status', 'value' => (string) $inv['status']], null) ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/print')) ?>">
                            <i class="bi bi-printer" aria-hidden="true"></i> <?= $e($t('admin.invoices.print')) ?>
                        </a>
                        <?php if (!empty($inv['imponibile'])): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/xml')) ?>"
                               title="<?= $e($t('admin.invoices.xml_download')) ?>">
                                <i class="bi bi-file-earmark-code" aria-hidden="true"></i> XML
                            </a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.projects.invoice_delete_confirm')) ?>">
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
