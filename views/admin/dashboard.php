<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var int $activeProjects */
/** @var int $openInterventions */
/** @var array<string,int> $todayByStatus */
/** @var array<int,array<string,mixed>> $lowStock */
/** @var array<int,array<string,mixed>> $expiringDocs */
/** @var string $today */
/** @var array|null $user */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$todayTotal = array_sum($todayByStatus);
$expiringDocs = $expiringDocs ?? [];
?>
<?php
// Localised long date (e.g. "lunedì 13 luglio 2026"), falling back gracefully
// if the intl extension is unavailable.
$heroDate = $today;
try {
    $d = new DateTimeImmutable($today);
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter('it_IT', IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $heroDate = $fmt->format($d) ?: $today;
    } else {
        // Manual Italian long date when the intl extension is unavailable.
        $days   = ['lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
        $months = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
                   'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        $heroDate = $days[(int) $d->format('N') - 1] . ' ' . (int) $d->format('j')
            . ' ' . $months[(int) $d->format('n') - 1] . ' ' . $d->format('Y');
    }
} catch (\Exception $ex) {
    $heroDate = $today;
}
$heroChips = [
    ['/admin/projects?status=active', (string) $activeProjects, 'admin.dashboard.hero_sites', 'bi-buildings'],
    ['/admin/interventions',          (string) $openInterventions, 'admin.dashboard.hero_interventions', 'bi-wrench'],
    ['/admin/compliance?expiring=1',  (string) count($expiringDocs), 'admin.dashboard.hero_deadlines', 'bi-exclamation-triangle'],
];
?>
<section class="app-hero mb-4">
    <div class="app-hero-body">
        <p class="app-hero-eyebrow"><?= $e($heroDate) ?></p>
        <h1 class="app-hero-title">
            <?= $e($t('admin.dashboard.hero_greeting')) ?> <?= $e($user['name'] ?? '') ?> <span aria-hidden="true">👷</span>
        </h1>
        <div class="app-hero-chips">
            <?php foreach ($heroChips as [$href, $num, $labelKey, $icon]): ?>
                <a class="app-hero-chip" href="<?= $e(Url::to($href)) ?>">
                    <i class="bi <?= $e($icon) ?>" aria-hidden="true"></i>
                    <span class="app-hero-chip-num"><?= $e($num) ?></span>
                    <?= $e($t($labelKey)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <i class="bi bi-buildings app-hero-glyph" aria-hidden="true"></i>
</section>

<?php
$kpis = [
    ['/admin/projects?status=active', (string) $activeProjects, 'admin.dashboard.active_projects', 'bi-buildings', false],
    ['/admin/interventions', (string) $openInterventions, 'admin.dashboard.open_interventions', 'bi-clipboard-check', false],
    ['/admin/interventions?range=today', (string) $todayTotal, 'admin.dashboard.today_interventions', 'bi-calendar-day', false],
    ['/admin/warehouse', (string) count($lowStock), 'admin.dashboard.low_stock', 'bi-box-seam', $lowStock !== []],
    ['/admin/compliance?expiring=1', (string) count($expiringDocs), 'admin.dashboard.expiring_docs', 'bi-shield-check', $expiringDocs !== []],
];
?>
<div class="row g-3">
    <?php foreach ($kpis as [$href, $val, $labelKey, $icon, $alert]): ?>
        <div class="col-6 col-lg-3 col-xl">
            <a class="card gm-kpi text-decoration-none h-100<?= $alert ? ' alert' : '' ?>" href="<?= $e(Url::to($href)) ?>">
                <div class="card-body">
                    <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                    <div class="gm-kpi-val mt-2"><?= $e($val) ?></div>
                    <div class="gm-kpi-lab"><?= $e($t($labelKey)) ?></div>
                    <?php if ($labelKey === 'admin.dashboard.today_interventions' && $todayTotal > 0): ?>
                        <div class="mt-2 d-flex flex-wrap gap-1">
                            <?php foreach ($todayByStatus as $status => $n): ?>
                                <span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $status)) ?>: <?= $e((string) $n) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<?php
$trends = $trends ?? [];
$trendCards = [
    ['admin.dashboard.trend_scheduled', $trends['scheduled'] ?? [], 'steel'],
    ['admin.dashboard.trend_completed', $trends['completed'] ?? [], 'ok'],
    ['admin.dashboard.trend_onsite', $trends['onsite'] ?? [], 'amber'],
];
?>
<h2 class="h6 text-muted mt-4 mb-2"><?= $e($t('admin.dashboard.trend_title')) ?></h2>
<div class="row g-3">
    <?php foreach ($trendCards as [$labelKey, $series, $color]): ?>
        <div class="col-12 col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-baseline gap-2">
                        <span class="gm-eyebrow"><?= $e($t($labelKey)) ?></span>
                        <span class="tnum fw-bold fs-5"><?= $e((string) array_sum($series)) ?></span>
                    </div>
                    <canvas class="gm-spark mt-2" height="34"
                            data-spark="<?= $e(implode(',', array_map('strval', $series))) ?>"
                            data-c="<?= $e($color) ?>"></canvas>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($expiringDocs !== []): ?>
    <div class="card mt-3 border-danger">
        <div class="card-header text-danger"><?= $e($t('admin.dashboard.expiring_title')) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= $e($t('admin.compliance.subject')) ?></th>
                        <th><?= $e($t('admin.compliance.doc_type')) ?></th>
                        <th><?= $e($t('admin.compliance.expiry')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($expiringDocs as $doc): ?>
                    <tr class="<?= $doc['expiry_date'] < $today ? 'sev-bad' : 'sev-warn' ?>">
                        <td>
                            <span class="badge text-bg-light border"><?= $e(Lang::label('compliance_subject', $doc['subject_type'])) ?></span>
                            <?= $e($doc['subject_name'] ?? ($doc['subject_type'] === 'company' ? $t('admin.compliance.the_company') : '—')) ?>
                        </td>
                        <td><?= $e(Lang::label('compliance_doc', $doc['doc_type'])) ?></td>
                        <td class="fw-bold <?= $doc['expiry_date'] < $today ? 'text-danger' : 'text-warning' ?>">
                            <?= $e($doc['expiry_date']) ?>
                            <?php if ($doc['expiry_date'] < $today): ?>
                                <span class="badge text-bg-danger"><?= $e($t('admin.compliance.expired')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/compliance')) ?>"><?= $e($t('admin.dashboard.open')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if ($lowStock !== []): ?>
    <div class="card mt-3 border-danger">
        <div class="card-header text-danger"><?= $e($t('admin.dashboard.low_stock_title')) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= $e($t('admin.warehouse.name')) ?></th>
                        <th><?= $e($t('admin.warehouse.qty_in_stock')) ?></th>
                        <th><?= $e($t('admin.warehouse.reorder_level')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStock as $item): ?>
                    <tr class="sev-bad">
                        <td><?= $e($item['name']) ?></td>
                        <td class="text-danger fw-bold"><?= $e($qty($item['qty_in_stock'])) ?> <?= $e(Lang::label('units', $item['unit'])) ?></td>
                        <td><?= $e($qty($item['reorder_level'])) ?> <?= $e(Lang::label('units', $item['unit'])) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse/' . $item['id'])) ?>"><?= $e($t('admin.dashboard.open')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php
// Quick actions: jump straight to the most-used "create" flows (not a copy of the
// sidebar's section navigation — these are one-click shortcuts to new records).
$quickActions = [
    ['/admin/projects/create',  'admin.projects.new',     'bi-buildings'],
    ['/admin/quotes/create',    'admin.quotes.new',       'bi-file-earmark-text'],
    ['/admin/invoices/create',  'admin.invoices.new',     'bi-receipt'],
    ['/admin/expenses/create',  'admin.expenses.new',     'bi-cash-coin'],
];
?>
<h2 class="h6 text-muted mt-4 mb-2"><?= $e($t('admin.dashboard.quick_actions')) ?></h2>
<div class="row g-3">
    <?php foreach ($quickActions as [$href, $labelKey, $icon]): ?>
        <div class="col-6 col-lg-3">
            <a class="card app-quick-action h-100 text-decoration-none" href="<?= $e(Url::to($href)) ?>">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="app-quick-action-icon"><i class="bi <?= $e($icon) ?>" aria-hidden="true"></i></span>
                    <span class="fw-semibold"><?= $e($t($labelKey)) ?></span>
                    <i class="bi bi-arrow-right ms-auto app-quick-action-arrow" aria-hidden="true"></i>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
