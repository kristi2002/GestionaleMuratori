<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<int,array<string,mixed>> $entries */
/** @var string $actionFilter        active action pill ('' = all) */
/** @var array<int,string> $actionOrder  known action keys in display order */
/** @var array<string,int> $actionCounts action => row count (real) */
/** @var array{total:int,today:int,users:int} $stats */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $k): string => Lang::get($k);

// Per-action presentation: status colour (title chip), pill dot, timeline icon.
$actionColor = [
    'created'     => 'app-status-success',
    'updated'     => 'app-status-info',
    'deleted'     => 'app-status-danger',
    'deactivated' => 'app-status-warning',
    'activated'   => 'app-status-success',
    'reset'       => 'app-status-neutral',
];
$actionDot = [
    'created'     => 'success',
    'updated'     => 'info',
    'deleted'     => 'danger',
    'deactivated' => 'warning',
    'activated'   => 'success',
    'reset'       => 'secondary',
];
$actionIcon = [
    'created'     => 'bi-plus-circle',
    'updated'     => 'bi-pencil',
    'deleted'     => 'bi-trash',
    'deactivated' => 'bi-slash-circle',
    'activated'   => 'bi-check-circle',
    'reset'       => 'bi-arrow-counterclockwise',
];

echo View::render('partials/page_head', [
    'title'    => $t('admin.audit.title'),
    'subtitle' => $t('admin.audit.subtitle'),
], null);

// KPI strip — cheap real aggregates over the audit_log table.
?>
<div class="app-kpi-grid mb-4">
    <div class="card gm-kpi is-primary h-100">
        <i class="bi bi-clock-history gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['total']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.audit.kpi_total')) ?></div>
    </div>
    <div class="card gm-kpi ok h-100">
        <i class="bi bi-calendar-check gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['today']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.audit.kpi_today')) ?></div>
    </div>
    <div class="card gm-kpi is-info h-100">
        <i class="bi bi-people gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['users']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.audit.kpi_users')) ?></div>
    </div>
</div>

<?php
// Action-type pill filters (Tutti + one per action that actually has entries),
// each wired to the real ?action= query param.
$pillHref = static fn (string $a): string => '/admin/audit' . ($a !== '' ? '?action=' . rawurlencode($a) : '');
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => $actionFilter === '',
    'count'  => $stats['total'],
]];
foreach ($actionOrder as $a) {
    if (($actionCounts[$a] ?? 0) === 0) {
        continue;
    }
    $pills[] = [
        'label'  => Lang::label('audit_actions', $a),
        'href'   => $pillHref($a),
        'active' => $actionFilter === $a,
        'count'  => $actionCounts[$a],
        'dot'    => $actionDot[$a] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);
?>

<?php if ($entries === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('admin.audit.empty'),
        'actions' => $actionFilter !== '' ? [[$t('common.all'), '/admin/audit']] : [],
    ], null) ?>
<?php else: ?>
    <div class="mb-3">
        <?php foreach ($entries as $a): ?>
            <?php
            $act    = (string) $a['action'];
            $ts     = (int) strtotime((string) $a['created_at']);
            $ip     = trim((string) ($a['ip'] ?? ''));
            $ent    = Lang::label('audit_entities', (string) $a['entity']);
            ?>
            <div class="app-timeline-item">
                <span class="app-timeline-icon">
                    <i class="bi <?= $e($actionIcon[$act] ?? 'bi-dot') ?>" aria-hidden="true"></i>
                </span>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="min-w-0">
                            <strong><?= $e($a['user_name'] ?? '—') ?></strong>
                            <span class="app-status <?= $e($actionColor[$act] ?? 'app-status-neutral') ?> ms-1"><?= $e(Lang::label('audit_actions', $act)) ?></span>
                        </div>
                        <span class="app-timeline-type">
                            <?= $e($ent) ?><?= $a['entity_id'] ? ' #' . $e((string) $a['entity_id']) : '' ?>
                        </span>
                    </div>
                    <?php if (($a['summary'] ?? '') !== ''): ?>
                        <p class="small mb-0 mt-1"><?= $e($a['summary']) ?></p>
                    <?php endif; ?>
                    <div class="small text-muted mt-1 d-flex flex-wrap gap-3">
                        <span>
                            <i class="bi bi-clock" aria-hidden="true"></i>
                            <?= $e(date('d/m/Y H:i', $ts)) ?>
                        </span>
                        <?php if ($ip !== ''): ?>
                            <span title="<?= $e($t('admin.audit.ip')) ?>">
                                <i class="bi bi-hdd-network" aria-hidden="true"></i>
                                <?= $e($ip) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
