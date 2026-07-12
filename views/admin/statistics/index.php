<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $stats */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');

// Semantic colours for the status donuts (fall back to slate grey).
$colors = [
    'project_status'      => ['active' => '#2e7d32', 'on_hold' => '#e07c10', 'closed' => '#adb5bd'],
    'intervention_status' => ['pending' => '#a3aebc', 'in_progress' => '#2c6e9b', 'on_hold' => '#e07c10', 'completed' => '#2e7d32', 'cancelled' => '#c0504d'],
    'invoice_status'      => ['draft' => '#adb5bd', 'issued' => '#2c6e9b', 'paid' => '#2e7d32'],
    'quote_status'        => ['draft' => '#adb5bd', 'sent' => '#2c6e9b', 'accepted' => '#2e7d32', 'rejected' => '#c0504d', 'expired' => '#e07c10'],
];
$palette = ['#2e7d32', '#2c6e9b', '#e07c10', '#7a5195', '#4aa3a2', '#c0504d'];

/** Build donut segments from a status=>count map. */
$statusDonut = static function (array $counts, string $group) use ($colors): array {
    $segs = [];
    foreach ($counts as $k => $v) {
        $segs[] = [
            'label'   => Lang::label($group, (string) $k),
            'value'   => (int) $v,
            'display' => (string) (int) $v,
            'color'   => $colors[$group][$k] ?? '#adb5bd',
        ];
    }
    return $segs;
};

$donut = static function (array $segments, string $centerNum) use ($t): void {
    echo View::render('partials/chart_donut', [
        'segments'  => $segments,
        'centerNum' => $centerNum,
        'empty'     => $t('admin.statistics.no_data'),
    ], null);
};

$kpi = $stats['kpi'];
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.statistics.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.statistics.subtitle')) ?></p>
    </div>
</div>

<!-- KPI row -->
<div class="app-kpi-grid mb-3">
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-sites"><i class="bi bi-buildings" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.statistics.kpi_active_projects')) ?></div>
            <div class="app-stat-value"><?= $e((string) (int) $kpi['active_projects']) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-pending"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.statistics.kpi_interventions_month')) ?></div>
            <div class="app-stat-value"><?= $e((string) (int) $kpi['interventions_month']) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip <?= $kpi['low_stock'] > 0 ? 'is-stock-alert' : 'is-stock' ?>"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.statistics.kpi_low_stock')) ?></div>
            <div class="app-stat-value <?= $kpi['low_stock'] > 0 ? 'is-alert' : '' ?>"><?= $e((string) (int) $kpi['low_stock']) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-crew"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.statistics.kpi_revenue')) ?></div>
            <div class="app-stat-value"><?= $e($money((float) $kpi['revenue_paid'])) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Projects by status -->
    <div class="col-12 col-lg-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.projects_by_status')) ?></div>
            <div class="card-body">
                <?php $donut($statusDonut($stats['projects_by_status'], 'project_status'), (string) array_sum($stats['projects_by_status'])); ?>
            </div>
        </div>
    </div>

    <!-- Interventions by status -->
    <div class="col-12 col-lg-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.interventions_by_status')) ?></div>
            <div class="card-body">
                <?php $donut($statusDonut($stats['interventions_by_status'], 'intervention_status'), (string) array_sum($stats['interventions_by_status'])); ?>
            </div>
        </div>
    </div>

    <!-- Quotes by status -->
    <div class="col-12 col-lg-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.quotes_by_status')) ?></div>
            <div class="card-body">
                <?php $donut($statusDonut($stats['quotes_by_status'], 'quote_status'), (string) array_sum($stats['quotes_by_status'])); ?>
            </div>
        </div>
    </div>

    <!-- Interventions per month -->
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.interventions_trend')) ?></div>
            <div class="card-body">
                <?= View::render('partials/chart_vbars', ['points' => $stats['interventions_by_month']], null) ?>
            </div>
        </div>
    </div>

    <!-- Invoices by status (with amounts in the legend) -->
    <div class="col-12 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.invoices_by_status')) ?></div>
            <div class="card-body">
                <?php
                $invSegs = [];
                foreach ($stats['invoices_by_status'] as $k => $row) {
                    $invSegs[] = [
                        'label'   => Lang::label('invoice_status', (string) $k),
                        'value'   => (int) $row['count'],
                        'display' => $money((float) $row['total']),
                        'color'   => $colors['invoice_status'][$k] ?? '#adb5bd',
                    ];
                }
                $donut($invSegs, (string) array_sum(array_map(static fn ($r) => (int) $r['count'], $stats['invoices_by_status'])));
                ?>
            </div>
        </div>
    </div>

    <!-- Expenses by category -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.expenses_by_category')) ?></div>
            <div class="card-body">
                <?php
                $expItems = [];
                $i = 0;
                foreach ($stats['expenses_by_category'] as $k => $total) {
                    $expItems[] = [
                        'label'   => Lang::label('expense_categories', (string) $k),
                        'value'   => (float) $total,
                        'display' => $money((float) $total),
                        'color'   => $palette[$i++ % count($palette)],
                    ];
                }
                echo View::render('partials/chart_hbars', ['items' => $expItems, 'empty' => $t('admin.statistics.no_data')], null);
                ?>
            </div>
        </div>
    </div>

    <!-- Top clients by project count -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.statistics.top_clients')) ?></div>
            <div class="card-body">
                <?php
                $clItems = [];
                $j = 0;
                foreach ($stats['top_clients'] as $c) {
                    $clItems[] = [
                        'label'   => $c['name'],
                        'value'   => (int) $c['count'],
                        'display' => (string) (int) $c['count'],
                        'color'   => $palette[$j++ % count($palette)],
                    ];
                }
                echo View::render('partials/chart_hbars', ['items' => $clItems, 'empty' => $t('admin.statistics.no_data')], null);
                ?>
            </div>
        </div>
    </div>
</div>
