<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $subcontractors  each with a 'project_ids' int[] */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,string> $compliance  subcontractor_id => 'expired'|'expiring'|'ok' */
/** @var array{total:int,active:int,on_sites:int} $stats */
/** @var string $search */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$compliance = $compliance ?? [];
// DURC/document gating: a compliance status badge per subcontractor.
$complianceBadge = static function (?string $status) use ($e, $t): string {
    $map = [
        'expired'  => ['app-status-danger',  'bi-exclamation-octagon-fill', 'admin.subcontractors.doc_expired'],
        'expiring' => ['app-status-warning', 'bi-exclamation-triangle-fill', 'admin.subcontractors.doc_expiring'],
        'ok'       => ['app-status-success', 'bi-check-circle-fill',         'admin.subcontractors.doc_ok'],
    ];
    if (!isset($map[$status])) {
        return '<span class="text-muted">—</span>';
    }
    [$cls, $icon, $key] = $map[$status];
    return '<span class="badge rounded-pill app-status ' . $cls . '"><i class="bi ' . $icon . '" aria-hidden="true"></i> '
        . $e($t($key)) . '</span>';
};
$blockedCount  = count(array_filter($compliance, static fn (string $s): bool => $s === 'expired'));
$expiringCount = count(array_filter($compliance, static fn (string $s): bool => $s === 'expiring'));
// Attention KPI accent: red if any DURC is expired, amber if only expiring.
$docKpiClass = $blockedCount > 0 ? ' alert' : ($expiringCount > 0 ? ' warn' : '');

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/subcontractors/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.subcontractors.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.subcontractors.title'),
    'subtitle' => $t('admin.subcontractors.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="app-kpi-grid mb-4">
    <div class="card gm-kpi is-info">
        <i class="bi bi-people gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['total']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.subcontractors.kpi_total')) ?></div>
    </div>
    <div class="card gm-kpi ok">
        <i class="bi bi-check2-circle gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['active']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.subcontractors.kpi_active')) ?></div>
    </div>
    <div class="card gm-kpi is-primary">
        <i class="bi bi-building-check gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) $stats['on_sites']) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.subcontractors.kpi_on_sites')) ?></div>
    </div>
    <div class="card gm-kpi<?= $docKpiClass ?>">
        <i class="bi bi-file-earmark-medical gm-kpi-ic" aria-hidden="true"></i>
        <div class="gm-kpi-val mt-2"><?= $e((string) ($blockedCount + $expiringCount)) ?></div>
        <div class="gm-kpi-lab"><?= $e($t('admin.subcontractors.kpi_docs')) ?></div>
    </div>
</div>

<?php if ($blockedCount > 0): ?>
    <div class="app-banner is-danger mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
        <span><?= $e(sprintf($t('admin.subcontractors.blocked_warning'), $blockedCount)) ?></span>
    </div>
<?php endif; ?>
<?php if ($expiringCount > 0): ?>
    <div class="app-banner is-warn mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <span><?= $e(sprintf($t('admin.subcontractors.expiring_warning'), $expiringCount)) ?></span>
    </div>
<?php endif; ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-2">
            <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $search !== '',
                'href'   => '/admin/subcontractors',
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
                    <th><?= $e($t('admin.subcontractors.name')) ?></th>
                    <th><?= $e($t('admin.subcontractors.vat')) ?></th>
                    <th><?= $e($t('admin.subcontractors.email')) ?></th>
                    <th><?= $e($t('admin.subcontractors.projects')) ?></th>
                    <th><?= $e($t('admin.subcontractors.compliance')) ?></th>
                    <th><?= $e($t('admin.subcontractors.active')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($subcontractors === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.subcontractors.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($subcontractors as $s): ?>
                <tr class="<?= ((int) $s['is_active']) === 1 ? '' : 'table-secondary text-muted' ?>">
                    <td>
                        <span class="d-inline-flex align-items-center gap-2">
                            <i class="bi bi-building" aria-hidden="true"></i>
                            <span class="fw-semibold"><?= $e($s['name']) ?></span>
                        </span>
                    </td>
                    <td><?= $e($s['vat_or_tax_id']) ?></td>
                    <td><?= $e($s['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $e((string) count($s['project_ids'])) ?></span></td>
                    <td><?= $complianceBadge($compliance[(int) $s['id']] ?? null) ?></td>
                    <td><?= ((int) $s['is_active']) === 1 ? $e($t('common.yes')) : $e($t('common.no')) ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#assign-modal-<?= $e((string) $s['id']) ?>">
                            <?= $e($t('admin.subcontractors.assign_projects')) ?>
                        </button>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/subcontractors/' . $s['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-warning js-toggle-active"
                                data-url="<?= $e(Url::to('/admin/subcontractors/' . $s['id'] . '/toggle')) ?>">
                            <?= ((int) $s['is_active']) === 1 ? $e($t('admin.subcontractors.deactivate')) : $e($t('admin.subcontractors.activate')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php /* Per-subcontractor project-assignment modal: checkboxes post as project_ids[]. */ ?>
<?php foreach ($subcontractors as $s): ?>
    <div class="modal fade" id="assign-modal-<?= $e((string) $s['id']) ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <?php /* No id field: js-crud-form posts to data-base-url verbatim. */ ?>
                <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/subcontractors/' . $s['id'] . '/projects')) ?>">
                    <div class="modal-header">
                        <h2 class="modal-title h5"><?= $e($t('admin.subcontractors.assign_projects')) ?> — <?= $e($s['name']) ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                        <?php if ($projects === []): ?>
                            <p class="text-muted mb-0"><?= $e($t('admin.projects.empty')) ?></p>
                        <?php endif; ?>
                        <?php foreach ($projects as $p): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="project_ids[]"
                                       value="<?= $e((string) $p['id']) ?>"
                                       id="assign-<?= $e((string) $s['id']) ?>-<?= $e((string) $p['id']) ?>"
                                       <?= in_array((int) $p['id'], $s['project_ids'], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="assign-<?= $e((string) $s['id']) ?>-<?= $e((string) $p['id']) ?>">
                                    <?= $e($p['name']) ?> <span class="text-muted small">— <?= $e($p['client_name']) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $e($t('common.cancel')) ?></button>
                        <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
