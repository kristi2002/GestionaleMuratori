<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array{rows:array<int,array<string,mixed>>,totals:array<string,float>,months:array<int,array{label:string,value:int}>} $finance */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';

$healthText = ['ok' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger', 'none' => 'text-muted'];
$healthBar  = ['ok' => 'var(--app-success)', 'warning' => '#F59E0B', 'danger' => '#EF4444', 'none' => '#94A3B8'];

$tot    = $finance['totals'];
$months = $finance['months'] ?? [];

// Current-month chip label, e.g. "Luglio 2026".
$fullMonths = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
$now       = new DateTimeImmutable('now');
$monthChip = $fullMonths[(int) $now->format('n')] . ' ' . $now->format('Y');

// Sparkline series for the "invoiced this month" card (trailing 12 months).
$sparkData = implode(',', array_map(static fn (array $m): string => (string) $m['value'], $months));

// Outstanding aggregated by client for the payment summary panel.
$byClient = [];
foreach ($finance['rows'] as $r) {
    $c = (string) $r['client_name'];
    $byClient[$c] = ($byClient[$c] ?? 0) + (float) $r['outstanding'];
}
arsort($byClient);
$topClients   = array_slice(array_filter($byClient, static fn ($v): bool => $v > 0), 0, 6, true);
$maxClientOut = $topClients ? max($topClients) : 0;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.financials.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.financials.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge rounded-pill app-status app-status-neutral">
            <i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= $e($monthChip) ?>
        </span>
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/invoices/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.financials.new_invoice')) ?>
        </a>
    </div>
</div>

<div class="app-kpi-grid mb-4">
    <div class="card gm-kpi ok">
        <i class="bi bi-graph-up-arrow gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e($money((float) ($tot['current_month'] ?? 0))) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.invoiced_month')) ?></div>
        <?php if ($sparkData !== '' && array_sum(array_column($months, 'value')) > 0): ?>
            <canvas class="gm-spark mt-2" height="34" data-spark="<?= $e($sparkData) ?>" data-c="ok"></canvas>
        <?php endif; ?>
    </div>
    <div class="card gm-kpi">
        <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e($money((float) $tot['collected'])) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.collected')) ?></div>
        <div class="gm-kpi-sub"><?= $e($t('admin.financials.invoiced')) ?>: <?= $e($money((float) $tot['invoiced'])) ?></div>
    </div>
    <div class="card gm-kpi warn">
        <i class="bi bi-clock-history gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e($money((float) $tot['outstanding'])) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.outstanding')) ?></div>
    </div>
    <div class="card gm-kpi<?= $tot['margin'] < 0 ? ' alert' : ' ok' ?>">
        <i class="bi bi-pie-chart gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e($money((float) $tot['margin'])) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.financials.margin')) ?></div>
        <div class="gm-kpi-sub"><?= $e($pct($tot['margin_pct'] ?? null)) ?></div>
    </div>
</div>

<?php if (array_sum(array_column($months, 'value')) > 0): ?>
    <div class="card mb-4">
        <div class="card-header"><?= $e($t('admin.financials.trend_title')) ?> <?= $e($now->format('Y')) ?></div>
        <div class="card-body">
            <?= View::render('partials/chart_vbars', ['points' => $months], null) ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
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
                    <?php if ($finance['rows'] === []): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.financials.empty')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($finance['rows'] as $r):
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
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('admin.financials.payments_title')) ?></div>
            <div class="card-body">
                <?php if ($topClients === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('admin.financials.no_outstanding')) ?></p>
                <?php else: ?>
                    <div class="app-hbars">
                        <?php foreach ($topClients as $client => $out):
                            $w = $maxClientOut > 0 ? round($out / $maxClientOut * 100, 1) : 0; ?>
                            <div class="app-payrow">
                                <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
                                    <span class="app-payrow-name text-truncate"><?= $e($client) ?></span>
                                    <span class="app-payrow-val"><?= $e($money((float) $out)) ?></span>
                                </div>
                                <div class="app-hbar-track">
                                    <div class="app-hbar-fill" style="width:<?= $w ?>%;background:#F59E0B"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
