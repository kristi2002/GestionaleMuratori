<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $expenses */
/** @var array{by_category: array<string,string>, total: string} $totals */
/** @var array<string,string> $monthByCategory current-month spend per category */
/** @var array<string,string> $byCategory chart aggregate: spend per category */
/** @var array<int,array{name:string,total:string}> $byProject chart aggregate: spend per project */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $projects */
/** @var array{search:string,category:string,worker_id:int,project_id:int,date_from:string,date_to:string} $filters */
/** @var array<int,string> $categories */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

$catIcons = [
    'meals'    => 'bi-cup-hot',
    'fuel'     => 'bi-fuel-pump',
    'vehicle'  => 'bi-truck',
    'clothing' => 'bi-bag',
    'other'    => 'bi-three-dots',
];
$catBadges = [
    'meals'    => 'text-bg-info',
    'fuel'     => 'text-bg-warning',
    'vehicle'  => 'text-bg-primary',
    'clothing' => 'text-bg-secondary',
    'other'    => 'text-bg-dark',
];
// Distinct hues per category, shared by the category bar chart and the donut.
$catColors = [
    'meals'    => '#3B82F6',
    'fuel'     => '#F59E0B',
    'vehicle'  => '#8B5CF6',
    'clothing' => '#14B8A6',
    'other'    => '#EF4444',
];
// Pill status dots reuse the same visual family as the table badges.
$catDots = [
    'meals'    => 'info',
    'fuel'     => 'warning',
    'vehicle'  => 'primary',
    'clothing' => 'secondary',
    'other'    => 'dark',
];

// Keep the current search/worker/project/date filters while switching category pill.
$pillHref = static function (string $category) use ($filters): string {
    $q = array_filter([
        'q'          => $filters['search'] ?? '',
        'worker_id'  => ($filters['worker_id'] ?? 0) ?: null,
        'project_id' => ($filters['project_id'] ?? 0) ?: null,
        'date_from'  => $filters['date_from'] ?? '',
        'date_to'    => $filters['date_to'] ?? '',
        'category'   => $category,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/expenses' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$expExportQ = http_build_query(array_filter([
    'q'          => $filters['search'] ?? '',
    'category'   => $filters['category'] ?? '',
    'worker_id'  => ($filters['worker_id'] ?? 0) ?: null,
    'project_id' => ($filters['project_id'] ?? 0) ?: null,
    'date_from'  => $filters['date_from'] ?? '',
    'date_to'    => $filters['date_to'] ?? '',
], static fn ($v): bool => $v !== '' && $v !== null));

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/expenses/export' . ($expExportQ !== '' ? '?' . $expExportQ : ''))) . '">'
    . '<i class="bi bi-download" aria-hidden="true"></i> ' . $e($t('common.export_csv')) . '</a>'
    . '<a class="btn btn-success" href="' . $e(Url::to('/admin/expenses/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.expenses.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.expenses.title'),
    'subtitle' => $t('admin.expenses.subtitle'),
    'actions'  => $actions,
], null);

// Category pill filters (Tutti + one per category), wired to ?category=.
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['category'] ?? '') === '',
]];
foreach ($categories as $cat) {
    $pills[] = [
        'label'  => Lang::label('expense_categories', $cat),
        'href'   => $pillHref($cat),
        'active' => ($filters['category'] ?? '') === $cat,
        'dot'    => $catDots[$cat] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);

// KPI row: current-month spend, overall then per category.
$monthTotal = 0.0;
foreach ($monthByCategory as $v) { $monthTotal += (float) $v; }
?>

<div class="app-kpi-grid mb-4">
    <div class="card gm-kpi is-primary h-100">
        <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e($money($monthTotal)) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.expenses.kpi_month_total')) ?></div>
    </div>
    <?php foreach ($categories as $cat): ?>
        <div class="card gm-kpi h-100">
            <i class="bi <?= $e($catIcons[$cat]) ?> gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($money($monthByCategory[$cat] ?? 0)) ?></div>
            <div class="gm-kpi-lab"><?= $e(Lang::label('expense_categories', $cat)) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php
// Chart data: per-category (bars + donut) and per-project (bars), from real aggregates.
$catData = [];
foreach ($categories as $cat) {
    $v = (float) ($byCategory[$cat] ?? 0);
    if ($v <= 0) { continue; }
    $catData[] = [
        'label'   => Lang::label('expense_categories', $cat),
        'value'   => $v,
        'display' => $money($v),
        'color'   => $catColors[$cat] ?? '#94A3B8',
    ];
}
$catTotal = 0.0;
foreach ($catData as $d) { $catTotal += (float) $d['value']; }

$projItems = [];
foreach ($byProject as $row) {
    $projItems[] = [
        'label'   => (string) $row['name'],
        'value'   => (float) $row['total'],
        'display' => $money($row['total']),
        'color'   => '#F97316',
    ];
}
?>
<?php if ($catData !== [] || $projItems !== []): ?>
<div class="row g-3 mb-4">
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.expenses.chart_by_category')) ?></div>
            <div class="card-body">
                <?= View::render('partials/chart_hbars', [
                    'items' => $catData,
                    'empty' => $t('admin.expenses.no_data'),
                ], null) ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.expenses.chart_distribution')) ?></div>
            <div class="card-body">
                <?= View::render('partials/chart_donut', [
                    'segments'  => $catData,
                    'centerNum' => $money($catTotal),
                    'empty'     => $t('admin.expenses.no_data'),
                ], null) ?>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.expenses.chart_by_project')) ?></div>
            <div class="card-body">
                <?= View::render('partials/chart_hbars', [
                    'items' => $projItems,
                    'empty' => $t('admin.expenses.no_data'),
                ], null) ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid">
            <select class="form-select" name="category" aria-label="<?= $e($t('admin.expenses.category')) ?>">
                <option value=""><?= $e($t('admin.expenses.category')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>>
                        <?= $e(Lang::label('expense_categories', $cat)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="worker_id" aria-label="<?= $e($t('admin.expenses.worker')) ?>">
                <option value=""><?= $e($t('admin.expenses.worker')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?= $e((string) $w['id']) ?>" <?= $filters['worker_id'] === (int) $w['id'] ? 'selected' : '' ?>>
                        <?= $e($w['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="app-date-range"
                 data-months="<?= $e(json_encode(array_map(static fn (int $m): string => Lang::label('months', (string) $m), range(1, 12)), JSON_UNESCAPED_UNICODE)) ?>"
                 data-weekdays="<?= $e(json_encode(array_map(static fn (int $d): string => Lang::label('weekdays_short', (string) $d), range(1, 7)), JSON_UNESCAPED_UNICODE)) ?>">
                <i class="bi bi-calendar3 app-date-range-icon" aria-hidden="true"></i>
                <label class="app-date-field">
                    <span class="app-date-prefix"><?= $e($t('admin.interventions.filter_date_from_short')) ?>:</span>
                    <input type="date" name="date_from" value="<?= $e($filters['date_from']) ?>"
                           aria-label="<?= $e($t('admin.interventions.filter_date_from')) ?>">
                </label>
                <span class="app-date-range-divider" aria-hidden="true"></span>
                <label class="app-date-field">
                    <span class="app-date-prefix"><?= $e($t('admin.interventions.filter_date_to_short')) ?>:</span>
                    <input type="date" name="date_to" value="<?= $e($filters['date_to']) ?>"
                           aria-label="<?= $e($t('admin.interventions.filter_date_to')) ?>">
                </label>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>"
                   placeholder="<?= $e($t('admin.expenses.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.interventions.project')) ?>">
                <option value=""><?= $e($t('admin.interventions.project')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?= View::render('partials/filter_clear', [
            'active' => $filters['search'] !== '' || $filters['category'] !== '' || $filters['worker_id'] > 0
                || $filters['project_id'] > 0 || $filters['date_from'] !== '' || $filters['date_to'] !== '',
            'href'   => '/admin/expenses',
        ], null) ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.expenses.date')) ?></th>
                    <th><?= $e($t('admin.expenses.category')) ?></th>
                    <th><?= $e($t('admin.expenses.description')) ?></th>
                    <th><?= $e($t('admin.expenses.worker')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.expenses.amount')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($expenses === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-cash-coin" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.expenses.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/expenses')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($expenses as $ex): ?>
                <tr>
                    <td><?= $e($date($ex['expense_date'])) ?></td>
                    <td>
                        <span class="badge <?= $e($catBadges[$ex['category']] ?? 'text-bg-secondary') ?>">
                            <i class="bi <?= $e($catIcons[$ex['category']] ?? 'bi-three-dots') ?>" aria-hidden="true"></i>
                            <?= $e(Lang::label('expense_categories', $ex['category'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= $e($ex['description']) ?>
                        <?php if ($ex['note']): ?>
                            <div class="small text-muted"><?= $e($ex['note']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($ex['worker_name'] ?? '—') ?></td>
                    <td><?= $e($ex['project_name'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= $e($money($ex['amount'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/expenses/' . $ex['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/expenses/' . $ex['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.expenses.delete_confirm')) ?>">
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
