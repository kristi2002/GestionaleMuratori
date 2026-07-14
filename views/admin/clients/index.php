<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $clients */
/** @var string $search */
/** @var \App\Support\Paginator $paginator */
/** @var int $projectsTotal */
/** @var float $invoicedTotal */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 0, ',', '.');

// Compact initials avatar from a client name (same pattern as projects/index.php).
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
        if (mb_strlen($ini) >= 2) { break; }
    }
    return $ini !== '' ? $ini : '—';
};

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/clients/export' . ($search !== '' ? '?q=' . rawurlencode($search) : ''))) . '">'
    . '<i class="bi bi-download" aria-hidden="true"></i> ' . $e($t('common.export_csv')) . '</a>'
    . '<a class="btn btn-success" href="' . $e(Url::to('/admin/clients/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.clients.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.clients.title'),
    'subtitle' => $t('admin.clients.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="card gm-kpi is-primary h-100">
            <i class="bi bi-people gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $paginator->total) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.clients.kpi_total')) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card gm-kpi h-100">
            <i class="bi bi-building gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) $projectsTotal) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.clients.kpi_projects')) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-cash-stack gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e($money((float) $invoicedTotal)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.clients.kpi_invoiced')) ?></div>
        </div>
    </div>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-2">
            <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $search !== '',
                'href'   => '/admin/clients',
                'inline' => true,
            ], null) ?>
        </form>
    </div>
</div>

<?php if ($clients === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('admin.clients.empty'),
        'actions' => array_merge(
            $search !== '' ? [[$t('common.reset_filters'), '/admin/clients']] : [],
            [[$t('admin.clients.new'), '/admin/clients/create', 'btn-success']]
        ),
    ], null) ?>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($clients as $c): ?>
            <?php
            $editUrl   = Url::to('/admin/clients/' . $c['id'] . '/edit');
            $mediaTint = ((int) $c['id'] % 2 === 0) ? ' tint-2' : '';
            $projCount = (int) ($c['project_count'] ?? 0);
            $invoiced  = (float) ($c['invoiced_total'] ?? 0);
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 app-record-card">
                    <div class="card-body d-flex flex-column">
                        <a href="<?= $e($editUrl) ?>" class="app-card-media<?= $mediaTint ?> mb-3 text-decoration-none">
                            <span class="app-avatar app-avatar-lg"><?= $e($initials((string) $c['name'])) ?></span>
                        </a>
                        <h2 class="h6 mb-1 text-truncate">
                            <a class="app-card-title-link" href="<?= $e($editUrl) ?>">
                                <?= $e($c['name']) ?>
                            </a>
                        </h2>
                        <?php if (($c['vat_or_tax_id'] ?? '') !== ''): ?>
                            <p class="small text-muted mb-2 text-truncate">
                                <i class="bi bi-hash" aria-hidden="true"></i>
                                <?= $e($c['vat_or_tax_id']) ?>
                            </p>
                        <?php endif; ?>
                        <ul class="list-unstyled small mb-3 app-card-meta">
                            <li>
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span class="text-truncate"><?= $e(($c['address'] ?? '') !== '' ? $c['address'] : '—') ?></span>
                            </li>
                            <?php if (($c['phone'] ?? '') !== ''): ?>
                                <li>
                                    <i class="bi bi-telephone" aria-hidden="true"></i>
                                    <span class="text-truncate"><?= $e($c['phone']) ?></span>
                                </li>
                            <?php endif; ?>
                            <?php if (($c['email'] ?? '') !== ''): ?>
                                <li>
                                    <i class="bi bi-envelope" aria-hidden="true"></i>
                                    <span class="text-truncate"><?= $e($c['email']) ?></span>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <div class="d-flex align-items-center gap-3 small text-muted mb-3">
                            <span><i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e((string) $projCount) ?> <?= $e($t('admin.clients.projects_count')) ?></span>
                            <?php if ($invoiced > 0): ?>
                                <span><i class="bi bi-cash-stack" aria-hidden="true"></i> <?= $e($money($invoiced)) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-auto pt-3 border-top">
                            <a class="btn btn-sm btn-success" href="<?= $e($editUrl) ?>">
                                <i class="bi bi-person-lines-fill" aria-hidden="true"></i> <?= $e($t('admin.clients.view_profile')) ?>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger app-icon-btn ms-auto js-crud-delete"
                                    title="<?= $e($t('common.delete')) ?>" aria-label="<?= $e($t('common.delete')) ?>"
                                    data-url="<?= $e(Url::to('/admin/clients/' . $c['id'] . '/delete')) ?>"
                                    data-confirm="<?= $e($t('admin.clients.delete_confirm')) ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
