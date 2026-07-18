<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $clients */
/** @var array{search:string,client_id:int,status:string} $filters */
/** @var array<int,string> $statuses */
/** @var array<string,int> $statusCounts */
/** @var int $totalCount */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Build a project-list URL that keeps the active search/client filter while
// switching the status pill (so the pills compose with the search box).
$pillHref = static function (string $status) use ($filters): string {
    $q = array_filter([
        'q'         => $filters['search'] ?? '',
        'client_id' => ($filters['client_id'] ?? 0) ?: null,
        'status'    => $status,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/projects' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$projExportQ = http_build_query(array_filter([
    'q'         => $filters['search'] ?? '',
    'client_id' => ($filters['client_id'] ?? 0) ?: null,
    'status'    => $filters['status'] ?? '',
], static fn ($v): bool => $v !== '' && $v !== null));

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/projects/export' . ($projExportQ !== '' ? '?' . $projExportQ : ''))) . '">'
    . '<i class="bi bi-download" aria-hidden="true"></i> ' . $e($t('common.export_csv')) . '</a>'
    . '<a class="btn btn-success" href="' . $e(Url::to('/admin/projects/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.projects.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.projects.title'),
    'subtitle' => $t('admin.projects.subtitle'),
    'actions'  => $actions,
], null);

// Status pill filters (Tutti + one per status, each with its real count).
$statusDots = ['active' => 'success', 'on_hold' => 'warning', 'closed' => 'secondary'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => ($filters['status'] ?? '') === '',
    'count'  => $totalCount,
]];
foreach ($statuses as $s) {
    $pills[] = [
        'label'  => Lang::label('project_status', $s),
        'href'   => $pillHref($s),
        'active' => ($filters['status'] ?? '') === $s,
        'count'  => $statusCounts[$s] ?? 0,
        'dot'    => $statusDots[$s] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);
?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-3">
            <?php if (($filters['status'] ?? '') !== ''): ?>
                <input type="hidden" name="status" value="<?= $e($filters['status']) ?>">
            <?php endif; ?>
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>" placeholder="<?= $e($t('admin.projects.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="client_id">
                <option value=""><?= $e($t('admin.projects.client')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $e((string) $c['id']) ?>" <?= ((int) $filters['client_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                        <?= $e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['search'] !== '' || $filters['client_id'] > 0,
                'href'   => $filters['status'] !== '' ? $pillHref($filters['status']) : '/admin/projects',
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
            <?php
            $workers = array_values(array_filter(array_map('trim', explode(',', (string) ($p['worker_names'] ?? '')))));
            $mediaTint = ((int) $p['id'] % 2 === 0) ? ' tint-2' : '';
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 app-record-card">
                    <div class="card-body d-flex flex-column">
                        <a href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>" class="app-card-media<?= $mediaTint ?> mb-3 text-decoration-none">
                            <i class="bi bi-building app-card-media-glyph" aria-hidden="true"></i>
                            <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $p['status']], null) ?>
                        </a>
                        <h2 class="h6 mb-1 text-truncate">
                            <a class="app-card-title-link" href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>">
                                <?= $e($p['name']) ?>
                            </a>
                        </h2>
                        <p class="small text-muted mb-2 text-truncate">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <?= $e($p['client_name']) ?>
                        </p>
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
                        </ul>
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <?php if ($workers !== []): ?>
                                <span class="app-avatars" title="<?= $e(implode(', ', $workers)) ?>">
                                    <?php foreach (array_slice($workers, 0, 3) as $w): ?>
                                        <span class="app-avatar"><?= $e(View::initials($w)) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($workers) > 3): ?>
                                        <span class="app-avatar is-more">+<?= $e((string) (count($workers) - 3)) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="small text-muted"><i class="bi bi-people" aria-hidden="true"></i> <?= $e($t('admin.projects.no_workers')) ?></span>
                            <?php endif; ?>
                        </div>
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

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
