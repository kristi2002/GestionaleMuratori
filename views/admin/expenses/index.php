<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $expenses */
/** @var array{by_category: array<string,string>, total: string} $totals */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $projects */
/** @var array{search:string,category:string,worker_id:int,project_id:int,date_from:string,date_to:string} $filters */
/** @var array<int,string> $categories */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';

$catIcons = [
    'meals'    => 'bi-cup-hot',
    'fuel'     => 'bi-fuel-pump',
    'vehicle'  => 'bi-truck',
    'clothing' => 'bi-bag',
    'other'    => 'bi-three-dots',
];
$catBadges = [
    'meals'    => 'text-bg-info',
    'fuel'     => 'text-bg-warning',
    'vehicle'  => 'text-bg-primary',
    'clothing' => 'text-bg-secondary',
    'other'    => 'text-bg-dark',
];
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.expenses.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.expenses.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/expenses/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.expenses.new')) ?>
        </a>
        <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
    </div>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.expenses.title'), null],
]], null) ?>

<?php // Totals of the current filter: one tile per category plus the grand total. ?>
<div class="row g-2 mb-3">
    <?php foreach ($categories as $cat): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="small text-muted text-truncate">
                        <i class="bi <?= $e($catIcons[$cat]) ?>" aria-hidden="true"></i>
                        <?= $e(Lang::label('expense_categories', $cat)) ?>
                    </div>
                    <div class="fw-semibold"><?= $e($money($totals['by_category'][$cat] ?? 0)) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card h-100 border-success">
            <div class="card-body py-2 px-3">
                <div class="small text-muted text-truncate">
                    <i class="bi bi-cash-stack" aria-hidden="true"></i>
                    <?= $e($t('admin.expenses.total')) ?>
                </div>
                <div class="fw-bold text-success"><?= $e($money($totals['total'])) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid">
            <select class="form-select" name="category" aria-label="<?= $e($t('admin.expenses.category')) ?>">
                <option value=""><?= $e($t('admin.expenses.category')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $e($cat) ?>" <?= $filters['category'] === $cat ? 'selected' : '' ?>>
                        <?= $e(Lang::label('expense_categories', $cat)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="worker_id" aria-label="<?= $e($t('admin.expenses.worker')) ?>">
                <option value=""><?= $e($t('admin.expenses.worker')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?= $e((string) $w['id']) ?>" <?= $filters['worker_id'] === (int) $w['id'] ? 'selected' : '' ?>>
                        <?= $e($w['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="app-date-range">
                <i class="bi bi-calendar3 app-date-range-icon" aria-hidden="true"></i>
                <label class="app-date-field">
                    <span class="app-date-prefix"><?= $e($t('admin.interventions.filter_date_from_short')) ?>:</span>
                    <input type="date" name="date_from" value="<?= $e($filters['date_from']) ?>"
                           aria-label="<?= $e($t('admin.interventions.filter_date_from')) ?>">
                </label>
                <span class="app-date-range-divider" aria-hidden="true"></span>
                <label class="app-date-field">
                    <span class="app-date-prefix"><?= $e($t('admin.interventions.filter_date_to_short')) ?>:</span>
                    <input type="date" name="date_to" value="<?= $e($filters['date_to']) ?>"
                           aria-label="<?= $e($t('admin.interventions.filter_date_to')) ?>">
                </label>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>"
                   placeholder="<?= $e($t('admin.expenses.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.interventions.project')) ?>">
                <option value=""><?= $e($t('admin.interventions.project')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?= View::render('partials/filter_clear', [
            'active' => $filters['search'] !== '' || $filters['category'] !== '' || $filters['worker_id'] > 0
                || $filters['project_id'] > 0 || $filters['date_from'] !== '' || $filters['date_to'] !== '',
            'href'   => '/admin/expenses',
        ], null) ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.expenses.date')) ?></th>
                    <th><?= $e($t('admin.expenses.category')) ?></th>
                    <th><?= $e($t('admin.expenses.description')) ?></th>
                    <th><?= $e($t('admin.expenses.worker')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.expenses.amount')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($expenses === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-cash-coin" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.expenses.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/expenses')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($expenses as $ex): ?>
                <tr>
                    <td><?= $e($date($ex['expense_date'])) ?></td>
                    <td>
                        <span class="badge <?= $e($catBadges[$ex['category']] ?? 'text-bg-secondary') ?>">
                            <i class="bi <?= $e($catIcons[$ex['category']] ?? 'bi-three-dots') ?>" aria-hidden="true"></i>
                            <?= $e(Lang::label('expense_categories', $ex['category'])) ?>
                        </span>
                    </td>
                    <td>
                        <?= $e($ex['description']) ?>
                        <?php if ($ex['note']): ?>
                            <div class="small text-muted"><?= $e($ex['note']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($ex['worker_name'] ?? '—') ?></td>
                    <td><?= $e($ex['project_name'] ?? '—') ?></td>
                    <td class="text-end fw-semibold"><?= $e($money($ex['amount'])) ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/expenses/' . $ex['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/expenses/' . $ex['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.expenses.delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
