<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $clients */
/** @var array{search:string,client_id:int,status:string} $filters */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.projects.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.projects.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/projects/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.projects.new')) ?>
        </a>
        <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
    </div>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.projects.title'), null],
]], null) ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-4">
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>" placeholder="<?= $e($t('admin.projects.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="client_id">
                <option value=""><?= $e($t('admin.projects.client')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $e((string) $c['id']) ?>" <?= ((int) $filters['client_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                        <?= $e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status">
                <option value=""><?= $e($t('admin.projects.status')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $e(Lang::label('project_status', $s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['search'] !== '' || $filters['client_id'] > 0 || $filters['status'] !== '',
                'href'   => '/admin/projects',
                'inline' => true,
            ], null) ?>
        </form>
    </div>
</div>

<?php $hasFilters = $filters['search'] !== '' || $filters['client_id'] > 0 || $filters['status'] !== ''; ?>
<?php if ($projects === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('admin.projects.empty'),
        'actions' => array_merge(
            $hasFilters ? [[$t('common.reset_filters'), '/admin/projects']] : [],
            [[$t('admin.projects.new'), '/admin/projects/create', 'btn-success']]
        ),
    ], null) ?>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($projects as $p): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 app-record-card">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                            <div class="min-w-0">
                                <h2 class="h6 mb-1 text-truncate">
                                    <a class="app-card-title-link" href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>">
                                        <?= $e($p['name']) ?>
                                    </a>
                                </h2>
                                <p class="small text-muted mb-0 text-truncate">
                                    <i class="bi bi-person" aria-hidden="true"></i>
                                    <?= $e($p['client_name']) ?>
                                </p>
                            </div>
                            <span class="flex-shrink-0">
                                <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $p['status']], null) ?>
                            </span>
                        </div>
                        <ul class="list-unstyled small mb-3 app-card-meta">
                            <li>
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span class="text-truncate"><?= $e(($p['location'] ?? '') !== '' ? $p['location'] : '—') ?></span>
                            </li>
                            <li>
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                <span>
                                    <?= $e($p['start_date']) ?><?= ($p['end_date'] ?? '') !== '' ? ' → ' . $e($p['end_date']) : '' ?>
                                </span>
                            </li>
                            <li>
                                <i class="bi bi-people" aria-hidden="true"></i>
                                <span class="text-truncate"><?= $e(($p['worker_names'] ?? '') !== '' && $p['worker_names'] !== null ? $p['worker_names'] : $t('admin.projects.no_workers')) ?></span>
                            </li>
                        </ul>
                        <div class="d-flex align-items-center gap-2 mt-auto pt-3 border-top">
                            <a class="btn btn-sm btn-success" href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>">
                                <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('common.open')) ?>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary app-icon-btn" href="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/report/pdf')) ?>"
                               title="<?= $e($t('report.pdf')) ?>" aria-label="<?= $e($t('report.pdf')) ?>">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary app-icon-btn" href="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/report/excel')) ?>"
                               title="<?= $e($t('report.excel')) ?>" aria-label="<?= $e($t('report.excel')) ?>">
                                <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger app-icon-btn ms-auto js-crud-delete"
                                    title="<?= $e($t('common.delete')) ?>" aria-label="<?= $e($t('common.delete')) ?>"
                                    data-url="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/delete')) ?>"
                                    data-confirm="<?= $e($t('admin.projects.delete_confirm')) ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

