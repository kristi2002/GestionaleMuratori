<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $client */
/** @var array{invoiced_total:float,paid_total:float,outstanding_total:float,projects_total:int,projects_active:int,last_payment_date:?string,next_deadline:?string} $stats */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $invoices */
/** @var array<int,array{label:string,value:int}> $monthly */
/** @var array<int,array<string,mixed>> $timeline */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$moneyK = static fn ($v): string => '€ ' . number_format((float) $v, 0, ',', '.');

// "Cliente da N anni" from created_at (real).
$yearsClient = null;
if (!empty($client['created_at'])) {
    try {
        $yearsClient = (new DateTimeImmutable((string) $client['created_at']))->diff(new DateTimeImmutable('today'))->y;
    } catch (\Exception $ex) {
        $yearsClient = null;
    }
}

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/projects/create?client_id=' . $client['id'])) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.clients.new_project')) . '</a>'
    . '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/clients/' . $client['id'] . '/edit')) . '">'
    . '<i class="bi bi-pencil" aria-hidden="true"></i> ' . $e($t('common.edit')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin/clients'], null);

echo View::render('partials/breadcrumb', ['items' => [
    [$t('admin.clients.title'), '/admin/clients'],
    [$t('admin.clients.profile_breadcrumb'), null],
]], null);

echo View::render('partials/page_head', [
    'title'    => (string) $client['name'],
    'subtitle' => ($client['vat_or_tax_id'] ?? '') !== '' ? $t('admin.clients.vat') . ': ' . $client['vat_or_tax_id'] : null,
    'actions'  => $actions,
], null);
?>

<div class="row g-4">
    <!-- Identity column ------------------------------------------------ -->
    <div class="col-12 col-lg-4">
        <div class="app-rail-card text-center mb-3">
            <span class="app-avatar app-avatar-lg mx-auto d-block" style="width:5rem;height:5rem;font-size:1.6rem;">
                <?= $e(View::initials((string) $client['name'])) ?>
            </span>
            <h2 class="h5 mt-3 mb-1"><?= $e($client['name']) ?></h2>
            <?php if ($yearsClient !== null): ?>
                <p class="small text-muted mb-0">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <?= $e($t('admin.clients.client_since')) ?> <?= $e((string) $yearsClient) ?> <?= $e($t('admin.clients.years_short')) ?>
                </p>
            <?php endif; ?>

            <div class="row g-2 mt-3 text-start">
                <?php
                $miniStats = [
                    [$t('admin.clients.stat_invoiced'), $moneyK($stats['invoiced_total']), 'is-primary'],
                    [$t('admin.clients.stat_outstanding'), $moneyK($stats['outstanding_total']), 'warn'],
                    [$t('admin.clients.projects_count'), (string) $stats['projects_total'], ''],
                    [$t('admin.clients.stat_paid'), $moneyK($stats['paid_total']), 'ok'],
                ];
                foreach ($miniStats as [$lab, $val, $variant]): ?>
                    <div class="col-6">
                        <div class="card gm-kpi h-100 <?= $e($variant) ?>">
                            <div class="card-body p-2">
                                <div class="gm-kpi-val" style="font-size:1.15rem;"><?= $e($val) ?></div>
                                <div class="gm-kpi-lab"><?= $e($lab) ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="app-rail-card mb-3">
            <h3 class="app-rail-title"><?= $e($t('admin.clients.contacts')) ?></h3>
            <dl class="app-dl">
                <?php if (($client['phone'] ?? '') !== ''): ?>
                    <div class="app-dl-row"><dt><i class="bi bi-telephone" aria-hidden="true"></i> <?= $e($t('admin.clients.phone')) ?></dt>
                        <dd><a href="tel:<?= $e($client['phone']) ?>"><?= $e($client['phone']) ?></a></dd></div>
                <?php endif; ?>
                <?php if (($client['email'] ?? '') !== ''): ?>
                    <div class="app-dl-row"><dt><i class="bi bi-envelope" aria-hidden="true"></i> <?= $e($t('admin.clients.email')) ?></dt>
                        <dd><a href="mailto:<?= $e($client['email']) ?>"><?= $e($client['email']) ?></a></dd></div>
                <?php endif; ?>
                <?php if (($client['address'] ?? '') !== ''): ?>
                    <div class="app-dl-row"><dt><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= $e($t('admin.clients.address')) ?></dt>
                        <dd><?= $e($client['address']) ?></dd></div>
                <?php endif; ?>
                <?php if (($client['vat_or_tax_id'] ?? '') !== ''): ?>
                    <div class="app-dl-row"><dt><i class="bi bi-hash" aria-hidden="true"></i> <?= $e($t('admin.clients.vat')) ?></dt>
                        <dd><?= $e($client['vat_or_tax_id']) ?></dd></div>
                <?php endif; ?>
            </dl>
        </div>

        <div class="app-rail-card">
            <h3 class="app-rail-title"><?= $e($t('admin.clients.notes')) ?></h3>
            <?php if (($client['notes'] ?? '') !== ''): ?>
                <p class="mb-3" style="white-space:pre-line;"><?= $e($client['notes']) ?></p>
            <?php else: ?>
                <p class="text-muted mb-3"><?= $e($t('admin.clients.no_note')) ?></p>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/clients/' . $client['id'] . '/edit')) ?>">
                <i class="bi bi-pencil-square" aria-hidden="true"></i> <?= $e($t('admin.clients.add_note')) ?>
            </a>
        </div>
    </div>

    <!-- Content column ------------------------------------------------- -->
    <div class="col-12 col-lg-8">
        <!-- Quick stats -->
        <div class="row g-3 mb-1">
            <?php
            $qNext = $stats['next_deadline'] ?? null;
            $qLast = $stats['last_payment_date'] ?? null;
            $quick = [
                [$t('admin.clients.stat_active_projects'), (string) $stats['projects_active'], 'bi-buildings', 'ok'],
                [$t('admin.clients.stat_outstanding'), $moneyK($stats['outstanding_total']), 'bi-hourglass-split', 'warn'],
                [$t('admin.clients.next_deadline'), $qNext !== null ? $e($qNext) : '—', 'bi-calendar-event', 'is-info'],
                [$t('admin.clients.last_payment'), $qLast !== null ? $e($qLast) : '—', 'bi-cash-coin', ''],
            ];
            foreach ($quick as [$lab, $val, $icon, $variant]): ?>
                <div class="col-6 col-xl-3">
                    <div class="card gm-kpi h-100 <?= $e($variant) ?>">
                        <div class="card-body">
                            <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                            <div class="gm-kpi-val mt-2" style="font-size:1.3rem;"><?= $val ?></div>
                            <div class="gm-kpi-lab"><?= $e($lab) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Revenue history chart -->
        <?php $hasRevenue = array_sum(array_column($monthly, 'value')) > 0; ?>
        <?php if ($hasRevenue): ?>
            <div class="card mt-3">
                <div class="card-header"><?= $e($t('admin.clients.revenue_history')) ?></div>
                <div class="card-body">
                    <?= View::render('partials/chart_line', ['points' => $monthly], null) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Projects -->
        <h2 class="app-section-title"><?= $e($t('admin.clients.tab_projects')) ?></h2>
        <?php if ($projects === []): ?>
            <div class="app-rail-empty"><?= $e($t('admin.clients.no_projects')) ?></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($projects as $p): ?>
                    <?php
                    $tot = (int) ($p['interv_total'] ?? 0);
                    $done = (int) ($p['interv_done'] ?? 0);
                    $pct = $tot > 0 ? (int) round($done / $tot * 100) : 0;
                    ?>
                    <div class="col-12 col-md-6">
                        <div class="card app-record-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <a class="app-card-title-link fw-semibold text-truncate" href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>"><?= $e($p['name']) ?></a>
                                    <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $p['status']], null) ?>
                                </div>
                                <p class="small text-muted mb-2 text-truncate">
                                    <i class="bi bi-geo-alt" aria-hidden="true"></i> <?= $e(($p['location'] ?? '') !== '' ? $p['location'] : '—') ?>
                                </p>
                                <?php if ($tot > 0): ?>
                                    <div class="app-meter">
                                        <div class="app-meter-track"><div class="app-meter-fill<?= $pct >= 100 ? ' is-success' : '' ?>" style="width:<?= $pct ?>%"></div></div>
                                        <span class="app-meter-val"><?= $pct ?>%</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Invoices -->
        <h2 class="app-section-title"><?= $e($t('admin.clients.tab_invoices')) ?></h2>
        <?php if ($invoices === []): ?>
            <div class="app-rail-empty"><?= $e($t('admin.clients.no_invoices')) ?></div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= $e($t('admin.clients.invoice_number')) ?></th>
                                <th><?= $e($t('admin.clients.invoice_project')) ?></th>
                                <th class="text-end"><?= $e($t('admin.clients.invoice_amount')) ?></th>
                                <th><?= $e(Lang::get('admin.projects.status')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($inv['number'] ?? '—') ?><div class="small text-muted"><?= $e($inv['issue_date'] ?? '') ?></div></td>
                                    <td class="text-truncate"><?= $e($inv['project_name'] ?? '') ?></td>
                                    <td class="text-end fw-semibold"><?= $e($money($inv['amount'] ?? 0)) ?></td>
                                    <td><?= View::render('partials/status_badge', ['group' => 'invoice_status', 'value' => (string) $inv['status']], null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Activity timeline -->
        <h2 class="app-section-title"><?= $e($t('admin.clients.activity')) ?></h2>
        <?php if ($timeline === []): ?>
            <div class="app-rail-empty"><?= $e($t('admin.clients.no_activity')) ?></div>
        <?php else: ?>
            <div class="card">
                <div class="card-body">
                    <?php
                    $evMeta = [
                        'invoice' => ['bi-receipt', 'ev_invoice'],
                        'quote'   => ['bi-file-earmark-text', 'ev_quote'],
                        'project' => ['bi-buildings', 'ev_project'],
                    ];
                    foreach ($timeline as $ev):
                        [$icon, $labKey] = $evMeta[$ev['type']] ?? ['bi-dot', 'ev_project'];
                        ?>
                        <div class="app-timeline-item d-flex align-items-center gap-3">
                            <span class="app-timeline-icon"><i class="bi <?= $e($icon) ?>" aria-hidden="true"></i></span>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">
                                    <?= $e($t('admin.clients.' . $labKey)) ?>
                                    <?php if (($ev['ref'] ?? '') !== ''): ?><span class="text-muted">· <?= $e($ev['ref']) ?></span><?php endif; ?>
                                </div>
                                <div class="small text-muted text-truncate"><?= $e($ev['title'] ?? '') ?> · <?= $e($ev['ev_date'] ?? '') ?></div>
                            </div>
                            <?php if ($ev['amount'] !== null): ?>
                                <span class="fw-bold flex-shrink-0"><?= $e($money($ev['amount'])) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
