<?php
use App\Support\Lang;
use App\Support\View;

/** @var array{rows:array<int,array<string,mixed>>,totals:array<string,float>} $finance */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 1, ',', '.') . '%';

$healthText = ['ok' => 'text-success', 'warning' => 'text-warning', 'danger' => 'text-danger', 'none' => 'text-muted'];
$healthBar  = ['ok' => 'var(--app-success)', 'warning' => '#F59E0B', 'danger' => '#EF4444', 'none' => '#94A3B8'];

$tot = $finance['totals'];
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.financials.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.financials.subtitle')) ?></p>
    </div>
</div>

<div class="app-kpi-grid mb-3">
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-pending"><i class="bi bi-receipt" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.financials.invoiced')) ?></div>
            <div class="app-stat-value"><?= $e($money((float) $tot['invoiced'])) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-sites"><i class="bi bi-cash-stack" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.financials.collected')) ?></div>
            <div class="app-stat-value"><?= $e($money((float) $tot['collected'])) ?></div>
            <div class="app-stat-unit"><?= $e($t('admin.financials.outstanding')) ?>: <?= $e($money((float) $tot['outstanding'])) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-stock-alert"><i class="bi bi-cart-dash" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.financials.costs')) ?></div>
            <div class="app-stat-value"><?= $e($money((float) $tot['cost'])) ?></div>
        </div>
    </div>
    <div class="app-stat-tile app-kpi-tile">
        <span class="app-stat-chip is-crew"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
        <div class="min-w-0">
            <div class="app-stat-label"><?= $e($t('admin.financials.margin')) ?></div>
            <div class="app-stat-value <?= $tot['margin'] < 0 ? 'is-alert' : '' ?>"><?= $e($money((float) $tot['margin'])) ?></div>
            <div class="app-stat-unit"><?= $e($pct($tot['margin_pct'] ?? null)) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span><?= $e($t('admin.financials.by_project')) ?></span>
        <span class="small text-muted fw-normal"><?= $e($t('admin.financials.margin_note')) ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.financials.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.invoiced')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.collected')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.outstanding')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.costs')) ?></th>
                    <th class="text-end"><?= $e($t('admin.financials.margin')) ?></th>
                    <th style="width:9rem;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($finance['rows'] === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.financials.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($finance['rows'] as $r):
                $ratio = $r['invoiced'] > 0 ? min(100, $r['cost'] / $r['invoiced'] * 100) : 0;
            ?>
                <tr>
                    <td>
                        <a href="<?= $e(\App\Support\Url::to('/admin/projects/' . $r['id'])) ?>" class="text-decoration-none fw-semibold"><?= $e($r['name']) ?></a>
                        <div class="small text-muted"><?= $e($r['client_name']) ?></div>
                    </td>
                    <td class="text-end"><?= $e($money((float) $r['invoiced'])) ?></td>
                    <td class="text-end"><?= $e($money((float) $r['collected'])) ?></td>
                    <td class="text-end <?= $r['outstanding'] > 0 ? 'text-warning' : 'text-muted' ?>"><?= $e($money((float) $r['outstanding'])) ?></td>
                    <td class="text-end"><?= $e($money((float) $r['cost'])) ?></td>
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
