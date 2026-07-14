<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array{rows:array<int,array<string,mixed>>,totals:array<string,float>,months:array<int,array{label:string,value:int}>} $finance */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');
$pct   = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';

// Compact currency for the KPI tiles / mini-cards (e.g. "€ 284 K"), matching the
// mockup — same real figures, abbreviated so the big mono numbers never overflow.
$moneyK = static function (float $v): string {
    $a = abs($v);
    if ($a >= 1_000_000) { return '€ ' . number_format($v / 1_000_000, 1, ',', '.') . ' Mln'; }
    if ($a >= 1_000)     { return '€ ' . number_format($v / 1_000, 1, ',', '.') . ' K'; }
    return '€ ' . number_format($v, 0, ',', '.');
};

$healthText = ['ok' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger', 'none' => 'text-muted'];
$healthBar  = ['ok' => 'var(--app-success)', 'warning' => 'var(--app-green)', 'danger' => 'var(--app-danger)', 'none' => '#94A3B8'];

$rows   = $finance['rows'];
$tot    = $finance['totals'];
$months = $finance['months'] ?? [];
$now    = new DateTimeImmutable('now');

// Revenue + margin aggregated by client for the "Top clienti" table (real values
// summed from the per-cantiere rows the service already computed).
$byClient = [];
foreach ($rows as $r) {
    $c = (string) $r['client_name'];
    if (!isset($byClient[$c])) { $byClient[$c] = ['invoiced' => 0.0, 'margin' => 0.0]; }
    $byClient[$c]['invoiced'] += (float) $r['invoiced'];
    $byClient[$c]['margin']   += (float) $r['margin'];
}
uasort($byClient, static fn (array $a, array $b): int => $b['invoiced'] <=> $a['invoiced']);
$topClients = array_slice(array_filter($byClient, static fn (array $v): bool => $v['invoiced'] > 0), 0, 5, true);

// Margin composition: how the revenue splits into cost vs. margin (real totals).
$compo = [
    ['label' => $t('admin.financials.invoiced'), 'value' => max(0.0, (float) $tot['invoiced']), 'display' => $moneyK((float) $tot['invoiced']), 'color' => 'var(--app-success)'],
    ['label' => $t('admin.financials.costs'),    'value' => max(0.0, (float) $tot['cost']),     'display' => $moneyK((float) $tot['cost']),     'color' => 'var(--app-danger)'],
    ['label' => $t('admin.financials.margin'),   'value' => max(0.0, (float) $tot['margin']),   'display' => $moneyK((float) $tot['margin']),   'color' => 'var(--app-info)'],
];

// Mini KPI cards (real averages over the cantieri that have figures).
$billed   = array_filter($rows, static fn (array $r): bool => (float) $r['invoiced'] > 0);
$withCost = array_filter($rows, static fn (array $r): bool => (float) $r['cost'] > 0);
$avgRev   = $billed   !== [] ? (float) $tot['invoiced'] / count($billed)   : 0.0;
$avgCost  = $withCost !== [] ? (float) $tot['cost']     / count($withCost) : 0.0;
$roi      = (float) $tot['cost'] > 0 ? (float) $tot['margin'] / (float) $tot['cost'] * 100 : null;

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/invoices/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.financials.new_invoice')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => $t('admin.financials.title'),
    'subtitle' => $t('admin.financials.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="app-kpi-grid mb-4">
    <div class="gm-kpi gm-kpi-solid is-success">
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.invoiced')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($moneyK((float) $tot['invoiced'])) ?></div>
    </div>
    <div class="gm-kpi gm-kpi-solid is-warn">
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.outstanding')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($moneyK((float) $tot['outstanding'])) ?></div>
    </div>
    <div class="gm-kpi gm-kpi-solid is-info">
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.avg_margin')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($pct($tot['margin_pct'] ?? null)) ?></div>
    </div>
    <div class="gm-kpi gm-kpi-solid is-purple">
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.costs')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($moneyK((float) $tot['cost'])) ?></div>
    </div>
</div>

<?php if (array_sum(array_column($months, 'value')) > 0): ?>
    <div class="card mb-4">
        <div class="card-header"><?= $e($t('admin.financials.trend_title')) ?> <?= $e($now->format('Y')) ?></div>
        <div class="card-body">
            <?= View::render('partials/chart_line', ['points' => $months], null) ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.financials.margin_composition')) ?></div>
            <div class="card-body">
                <?= View::render('partials/chart_hbars', ['items' => $compo, 'empty' => $t('admin.financials.empty')], null) ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.financials.top_clients')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= $e($t('admin.financials.client')) ?></th>
                            <th class="text-end"><?= $e($t('admin.financials.invoiced')) ?></th>
                            <th class="text-end"><?= $e($t('admin.financials.margin')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($topClients === []): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4"><?= $e($t('admin.financials.no_clients')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($topClients as $client => $agg):
                        $mPct = $agg['invoiced'] > 0 ? $agg['margin'] / $agg['invoiced'] * 100 : null;
                        $mCls = $mPct === null ? 'text-muted' : ($mPct < 0 ? 'text-danger' : 'text-success');
                    ?>
                        <tr>
                            <td class="fw-semibold text-truncate"><?= $e((string) $client) ?></td>
                            <td class="text-end"><?= $e($money((float) $agg['invoiced'])) ?></td>
                            <td class="text-end fw-semibold <?= $e($mCls) ?>"><?= $e($pct($mPct)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-building gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($moneyK($avgRev)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.financials.avg_project_revenue')) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card gm-kpi is-purple h-100">
            <i class="bi bi-cash-coin gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($moneyK($avgCost)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.financials.avg_project_cost')) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card gm-kpi<?= ($roi !== null && $roi < 0) ? ' alert' : ' is-info' ?> h-100">
            <i class="bi bi-graph-up-arrow gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($pct($roi)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.financials.roi')) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><?= $e($t('admin.financials.by_project')) ?></span>
        <span class="small text-muted fw-normal d-none d-md-inline"><?= $e($t('admin.financials.margin_note')) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.financials.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.invoiced')) ?></th>
                    <th class="text-end d-none d-md-table-cell"><?= $e($t('admin.financials.collected')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.outstanding')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.margin')) ?></th>
                    <th style="width:7rem;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.financials.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r):
                $ratio = $r['invoiced'] > 0 ? min(100, $r['cost'] / $r['invoiced'] * 100) : 0;
            ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/projects/' . $r['id'])) ?>" class="app-card-title-link fw-semibold"><?= $e($r['name']) ?></a>
                        <div class="small text-muted"><?= $e($r['client_name']) ?></div>
                    </td>
                    <td class="text-end"><?= $e($money((float) $r['invoiced'])) ?></td>
                    <td class="text-end d-none d-md-table-cell"><?= $e($money((float) $r['collected'])) ?></td>
                    <td class="text-end <?= $r['outstanding'] > 0 ? 'text-warning' : 'text-muted' ?>"><?= $e($money((float) $r['outstanding'])) ?></td>
                    <td class="text-end fw-semibold <?= $e($healthText[$r['health']] ?? '') ?>">
                        <?= $e($money((float) $r['margin'])) ?>
                        <div class="small fw-normal <?= $e($healthText[$r['health']] ?? '') ?>"><?= $e($pct($r['margin_pct'])) ?></div>
                    </td>
                    <td>
                        <div class="app-hbar-track" title="<?= $e($t('admin.financials.cost_ratio')) ?>">
                            <div class="app-hbar-fill" style="width:<?= round($ratio, 1) ?>%;background:<?= $e($healthBar[$r['health']] ?? 'var(--app-green)') ?>"></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
