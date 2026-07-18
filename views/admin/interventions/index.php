<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $interventions */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $warehouseItems */
/** @var array{project_id:int,worker_id:int,status:string} $filters */
/** @var array<int,string> $statuses */
/** @var array<string,int> $statusCounts */
/** @var int $totalCount */
/** @var array{today:int,week:int,overdue:int,completed_month:int} $kpis */
/** @var string $range */
/** @var string $dateFilter */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$dateFilter = $dateFilter ?? '';

$rangeLink = static function (string $value) use ($filters): string {
    $query = array_filter([
        'project_id' => $filters['project_id'] ?: null,
        'worker_id'  => $filters['worker_id'] ?: null,
        'status'     => $filters['status'] ?: null,
        'range'      => $value ?: null,
    ]);
    return Url::to('/admin/interventions') . ($query !== [] ? '?' . http_build_query($query) : '');
};

// Pill href: keeps project/worker/range while switching the status filter.
$pillHref = static function (string $status) use ($filters, $range): string {
    $query = array_filter([
        'project_id' => $filters['project_id'] ?: null,
        'worker_id'  => $filters['worker_id'] ?: null,
        'status'     => $status ?: null,
        'range'      => $range ?: null,
    ]);
    return '/admin/interventions' . ($query !== [] ? '?' . http_build_query($query) : '');
};

/** Status transitions an admin can trigger from the list (completed is worker-only, Phase 5). */
$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.start')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('admin.interventions.hold')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.resume')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
];

$exportQ = http_build_query(array_filter([
    'project_id' => $filters['project_id'] ?: null,
    'worker_id'  => $filters['worker_id'] ?: null,
    'status'     => $filters['status'] ?: null,
    'range'      => $range ?: null,
]));

$actions = '<a class="btn btn-outline-secondary app-icon-btn" href="' . $e(Url::to('/admin/interventions/calendar')) . '"'
    . ' title="' . $e($t('admin.interventions.calendar_view')) . '" aria-label="' . $e($t('admin.interventions.calendar_view')) . '">'
    . '<i class="bi bi-calendar3" aria-hidden="true"></i></a>'
    . '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/interventions/export' . ($exportQ !== '' ? '?' . $exportQ : ''))) . '">'
    . '<i class="bi bi-download" aria-hidden="true"></i> ' . $e($t('common.export_csv')) . '</a>'
    . '<a class="btn btn-success" href="' . $e(Url::to('/admin/interventions/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.interventions.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.interventions.title'),
    'subtitle' => $t('admin.interventions.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-primary h-100">
            <i class="bi bi-calendar-day gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $kpis['today']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.interventions.kpi_today')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-info h-100">
            <i class="bi bi-calendar-week gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $kpis['week']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.interventions.kpi_week')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-danger h-100">
            <i class="bi bi-exclamation-triangle gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $kpis['overdue']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.interventions.kpi_overdue')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-check2-circle gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $kpis['completed_month']) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.interventions.kpi_completed_month')) ?></div>
        </div>
    </div>
</div>

<?php
// Status pill filters (Tutti + one per status, with real counts).
$statusDots = ['pending' => 'secondary', 'in_progress' => 'info', 'on_hold' => 'warning', 'completed' => 'success', 'cancelled' => 'danger'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['status'] ?? '') === '',
    'count'  => $totalCount,
]];
foreach ($statuses as $s) {
    $pills[] = [
        'label'  => Lang::label('intervention_status', $s),
        'href'   => $pillHref($s),
        'active' => ($filters['status'] ?? '') === $s,
        'count'  => $statusCounts[$s] ?? 0,
        'dot'    => $statusDots[$s] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);
?>

<?php if ($dateFilter !== ''): ?>
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 mb-3">
        <span><i class="bi bi-calendar-event me-1" aria-hidden="true"></i><?= $e(sprintf($t('admin.interventions.date_filter'), $dateFilter)) ?></span>
        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions')) ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i> <?= $e($t('admin.interventions.date_filter_clear')) ?>
        </a>
    </div>
<?php endif; ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="<?= $e($t('admin.interventions.title')) ?>">
            <a class="btn <?= $range === 'today' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('today')) ?>"><?= $e($t('admin.interventions.range_today')) ?></a>
            <a class="btn <?= $range === 'week' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('week')) ?>"><?= $e($t('admin.interventions.range_week')) ?></a>
            <a class="btn <?= $range === '' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('')) ?>"><?= $e($t('admin.interventions.range_all')) ?></a>
        </div>
        <form method="get" class="app-filter-grid app-filter-grid-selects">
            <input type="hidden" name="range" value="<?= $e($range) ?>">
            <?php if (($filters['status'] ?? '') !== ''): ?>
                <input type="hidden" name="status" value="<?= $e($filters['status']) ?>">
            <?php endif; ?>
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.interventions.project')) ?>">
                <option value=""><?= $e($t('admin.interventions.project')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= ((int) $filters['project_id'] === (int) $p['id']) ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="worker_id" aria-label="<?= $e($t('admin.interventions.worker')) ?>">
                <option value=""><?= $e($t('admin.interventions.worker')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?= $e((string) $w['id']) ?>" <?= ((int) $filters['worker_id'] === (int) $w['id']) ? 'selected' : '' ?>><?= $e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['project_id'] > 0 || $filters['worker_id'] > 0,
                'href'   => $filters['status'] !== '' ? $pillHref($filters['status']) : ('/admin/interventions' . ($range !== '' ? '?range=' . $range : '')),
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
                    <th><?= $e($t('admin.interventions.field_title')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th><?= $e($t('admin.interventions.worker')) ?></th>
                    <th><?= $e($t('admin.interventions.scheduled_date')) ?></th>
                    <th><?= $e($t('admin.interventions.materials')) ?></th>
                    <th><?= $e($t('admin.interventions.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($interventions === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.interventions.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($interventions as $iv): ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/interventions/' . $iv['id'])) ?>" class="text-decoration-none"><?= $e($iv['title']) ?></a>
                        <div class="small text-muted"><?= $e($iv['client_name']) ?></div>
                    </td>
                    <td><?= $e($iv['project_name']) ?></td>
                    <td><?= $e($iv['worker_name'] ?? $t('admin.interventions.unassigned')) ?></td>
                    <td class="small">
                        <?= $e($iv['scheduled_date']) ?>
                        <?= $iv['scheduled_start_time'] ? ' ' . $e(substr((string) $iv['scheduled_start_time'], 0, 5)) : '' ?>
                    </td>
                    <td class="small">
                        <?php if ($iv['materials'] === []): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?php foreach ($iv['materials'] as $m): ?>
                                <div><?= $e($m['item_name']) ?>: <?= $e(rtrim(rtrim((string) $m['qty_planned'], '0'), '.')) ?> <?= $e(Lang::label('units', $m['unit'])) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $iv['status']], null) ?></td>
                    <td class="text-end app-row-actions">
                        <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/' . $iv['id'] . '/edit')) ?>">
                                <?= $e($t('common.edit')) ?>
                            </a>
                            <?php foreach ($nextActions[$iv['status']] ?? [] as $action): ?>
                                <button type="button" class="btn btn-sm <?= $action['to'] === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-intervention-status"
                                        data-url="<?= $e(Url::to('/admin/interventions/' . $iv['id'] . '/status')) ?>"
                                        data-to-status="<?= $e($action['to']) ?>"
                                        <?= $action['to'] === 'cancelled' ? 'data-confirm="' . $e($t('admin.interventions.cancel_confirm')) . '"' : '' ?>>
                                    <?= $e($action['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
