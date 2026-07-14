<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $stats */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');

// Semantic colours for the status donuts (fall back to slate grey).
$colors = [
    'project_status'      => ['active' => '#10B981', 'on_hold' => '#F59E0B', 'closed' => '#94A3B8'],
    'intervention_status' => ['pending' => '#94A3B8', 'in_progress' => '#3B82F6', 'on_hold' => '#F59E0B', 'completed' => '#10B981', 'cancelled' => '#EF4444'],
    'invoice_status'      => ['draft' => '#94A3B8', 'issued' => '#3B82F6', 'paid' => '#10B981'],
    'quote_status'        => ['draft' => '#94A3B8', 'sent' => '#3B82F6', 'accepted' => '#10B981', 'rejected' => '#EF4444', 'expired' => '#F59E0B'],
];
$palette = ['#F97316', '#3B82F6', '#10B981', '#8B5CF6', '#14B8A6', '#EF4444'];

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

echo View::render('partials/page_head', [
    'title'    => $t('admin.statistics.title'),
    'subtitle' => $t('admin.statistics.subtitle'),
], null);

// KPI row — every value is a real aggregate from StatisticsService (no fabricated
// trends: the controller computes no prior-period delta, so the trend line is omitted).
$kpiCards = [
    ['is-info',   'bi-buildings',       $t('admin.statistics.kpi_total_projects'),      (string) (int) $kpi['total_projects']],
    ['ok',        'bi-cash-stack',      $t('admin.statistics.kpi_revenue'),             $money((float) $kpi['revenue_paid'])],
    ['is-purple', 'bi-people',          $t('admin.statistics.kpi_workers'),             (string) (int) $kpi['total_workers']],
    ['warn',      'bi-clipboard-check', $t('admin.statistics.kpi_interventions_total'), (string) (int) $kpi['total_interventions']],
];
?>
<!-- KPI row -->
<div class="row g-3 mb-3">
    <?php foreach ($kpiCards as [$variant, $icon, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="card gm-kpi h-100 <?= $e($variant) ?>">
                <div class="card-body">
                    <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                    <div class="gm-kpi-val mt-2"><?= $e($val) ?></div>
                    <div class="gm-kpi-lab"><?= $e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
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
                <?= View::render('partials/chart_line', ['points' => $stats['interventions_by_month']], null) ?>
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
