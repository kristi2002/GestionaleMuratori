<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var string $currentMonth  YYYY-MM */
/** @var array<int,array<string,mixed>> $projects */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$projects = $projects ?? [];

echo View::render('partials/page_head', [
    'title'    => $t('admin.exports.title'),
    'subtitle' => $t('admin.exports.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin'], null),
], null);

// One-click CSV exports — each links to a real, already-registered export route.
$csvCards = [
    ['icon' => 'bi-buildings',           'name' => 'admin.exports.projects_csv',      'help' => 'admin.exports.projects_csv_help',      'href' => '/admin/projects/export'],
    ['icon' => 'bi-people',              'name' => 'admin.exports.clients_csv',       'help' => 'admin.exports.clients_csv_help',       'href' => '/admin/clients/export'],
    ['icon' => 'bi-clipboard-check',     'name' => 'admin.exports.interventions_csv', 'help' => 'admin.exports.interventions_csv_help', 'href' => '/admin/interventions/export'],
    ['icon' => 'bi-cash-coin',           'name' => 'admin.exports.expenses_csv',      'help' => 'admin.exports.expenses_csv_help',      'href' => '/admin/expenses/export'],
];
?>

<h2 class="app-section-title"><?= $e($t('admin.exports.quick_title')) ?></h2>

<div class="row g-3">
    <?php // Monthly accountant workbook (.xlsx) — needs a month, so an inline form. ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 app-quick-action text-center">
            <div class="card-body d-flex flex-column align-items-center">
                <span class="badge text-bg-success align-self-end"><?= $e($t('admin.exports.format_excel')) ?></span>
                <i class="bi bi-file-earmark-spreadsheet fs-1 text-success mb-2" aria-hidden="true"></i>
                <h3 class="h6 mb-1"><?= $e($t('admin.exports.accountant')) ?></h3>
                <p class="small text-muted mb-3"><?= $e($t('admin.exports.accountant_help')) ?></p>
                <form method="get" action="<?= $e(Url::to('/admin/exports/accountant')) ?>"
                      class="mt-auto w-100 d-flex flex-column align-items-center gap-2">
                    <label class="visually-hidden" for="exp-month"><?= $e($t('admin.exports.month')) ?></label>
                    <input type="month" id="exp-month" class="form-control" name="month"
                           value="<?= $e($currentMonth) ?>" required>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-download" aria-hidden="true"></i> <?= $e($t('admin.exports.download')) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php foreach ($csvCards as $c): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 app-quick-action text-center">
                <div class="card-body d-flex flex-column align-items-center">
                    <span class="badge text-bg-secondary align-self-end"><?= $e($t('admin.exports.format_csv')) ?></span>
                    <i class="bi <?= $e($c['icon']) ?> fs-1 text-success mb-2" aria-hidden="true"></i>
                    <h3 class="h6 mb-1"><?= $e($t($c['name'])) ?></h3>
                    <p class="small text-muted mb-3"><?= $e($t($c['help'])) ?></p>
                    <a class="btn btn-success w-100 mt-auto" href="<?= $e(Url::to($c['href'])) ?>">
                        <i class="bi bi-download" aria-hidden="true"></i> <?= $e($t('admin.exports.export_action')) ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="app-section-title"><?= $e($t('admin.exports.custom_title')) ?></h2>

<div class="card">
    <div class="card-body">
        <h3 class="h6 mb-1">
            <i class="bi bi-buildings text-success" aria-hidden="true"></i>
            <?= $e($t('admin.exports.project_report')) ?>
        </h3>
        <p class="small text-muted mb-3"><?= $e($t('admin.exports.project_report_help')) ?></p>
        <?php if ($projects === []): ?>
            <p class="text-muted small mb-0"><?= $e($t('admin.exports.no_projects')) ?></p>
        <?php else: ?>
            <div class="d-flex flex-wrap align-items-center gap-2 js-export-project-row"
                 data-base="<?= $e(Url::to('/admin/projects')) ?>">
                <label class="visually-hidden" for="exp-project"><?= $e($t('admin.exports.select_project')) ?></label>
                <select id="exp-project" class="form-select w-auto js-export-project">
                    <option value=""><?= $e($t('admin.exports.select_project')) ?></option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>"><?= $e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary js-export-project-btn" data-format="pdf">
                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= $e($t('admin.exports.format_pdf')) ?>
                    </button>
                    <button type="button" class="btn btn-outline-secondary js-export-project-btn" data-format="excel">
                        <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> <?= $e($t('admin.exports.format_excel')) ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
