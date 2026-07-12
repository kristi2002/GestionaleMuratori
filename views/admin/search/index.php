<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var string $q */
/** @var array<string,array<int,array<string,mixed>>> $results */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$groupLabel = [
    'projects'       => 'admin.projects.title',
    'interventions'  => 'admin.interventions.title',
    'clients'        => 'admin.clients.title',
    'subcontractors' => 'admin.subcontractors.title',
    'warehouse'      => 'admin.warehouse.title',
];
$groupIcon = [
    'projects'       => 'bi-buildings',
    'interventions'  => 'bi-calendar-week',
    'clients'        => 'bi-people',
    'subcontractors' => 'bi-diagram-3',
    'warehouse'      => 'bi-box-seam',
];
?>
<h1 class="h4 mb-3"><?= $e($t('admin.search.title')) ?></h1>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" action="<?= $e(Url::to('/admin/search')) ?>">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="text" class="form-control" name="q" value="<?= $e($q) ?>"
                       placeholder="<?= $e($t('admin.search.placeholder')) ?>" autofocus aria-label="<?= $e($t('admin.search.title')) ?>">
                <button type="submit" class="btn btn-success"><?= $e($t('common.search')) ?></button>
            </div>
        </form>
    </div>
</div>

<?php if ($q === ''): ?>
    <p class="text-muted"><?= $e($t('admin.search.hint')) ?></p>
<?php elseif ($results === []): ?>
    <div class="app-empty-state">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p class="mb-0"><?= $e(sprintf($t('admin.search.no_results'), $q)) ?></p>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($results as $group => $rows): ?>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white fw-semibold d-flex align-items-center justify-content-between">
                        <span><i class="bi <?= $e($groupIcon[$group] ?? 'bi-dot') ?> text-success" aria-hidden="true"></i> <?= $e($t($groupLabel[$group] ?? $group)) ?></span>
                        <span class="badge text-bg-light border"><?= $e((string) count($rows)) ?></span>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php foreach ($rows as $r): ?>
                            <a href="<?= $e(Url::to((string) $r['url'])) ?>" class="list-group-item list-group-item-action d-flex align-items-center justify-content-between gap-2">
                                <span class="min-w-0">
                                    <span class="fw-semibold d-block text-truncate"><?= $e((string) $r['title']) ?></span>
                                    <?php if (($r['subtitle'] ?? '') !== ''): ?>
                                        <span class="small text-muted d-block text-truncate"><?= $e((string) $r['subtitle']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($r['status'])): ?>
                                    <?= View::render('partials/status_badge', $r['status'], null) ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
