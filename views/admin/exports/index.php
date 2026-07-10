<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var string $currentMonth  YYYY-MM */
/** @var array<int,array<string,mixed>> $projects */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$projects = $projects ?? [];
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.exports.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.exports.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.exports.title'), null],
]], null) ?>

<div class="card">
    <div class="card-header"><?= $e($t('admin.exports.available')) ?></div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.exports.export_col')) ?></th>
                    <th><?= $e($t('admin.exports.description_col')) ?></th>
                    <th style="min-width: 260px;"><?= $e($t('admin.exports.options_col')) ?></th>
                    <th class="text-end"><?= $e($t('admin.exports.action_col')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php // 1) Monthly accountant workbook (Prima Nota). ?>
                <tr>
                    <td class="fw-semibold">
                        <i class="bi bi-file-earmark-spreadsheet text-success" aria-hidden="true"></i>
                        <?= $e($t('admin.exports.accountant')) ?>
                    </td>
                    <td class="small text-muted"><?= $e($t('admin.exports.accountant_help')) ?></td>
                    <td colspan="2">
                        <form method="get" action="<?= $e(Url::to('/admin/exports/accountant')) ?>"
                              class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                            <label class="visually-hidden" for="exp-month"><?= $e($t('admin.exports.month')) ?></label>
                            <input type="month" id="exp-month" class="form-control w-auto" name="month"
                                   value="<?= $e($currentMonth) ?>" required>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-download" aria-hidden="true"></i> <?= $e($t('admin.exports.download')) ?>
                            </button>
                        </form>
                    </td>
                </tr>

                <?php // 2) Per-project report (reuses the existing project report endpoints). ?>
                <tr>
                    <td class="fw-semibold">
                        <i class="bi bi-buildings text-success" aria-hidden="true"></i>
                        <?= $e($t('admin.exports.project_report')) ?>
                    </td>
                    <td class="small text-muted"><?= $e($t('admin.exports.project_report_help')) ?></td>
                    <?php if ($projects === []): ?>
                        <td colspan="2" class="text-muted small"><?= $e($t('admin.exports.no_projects')) ?></td>
                    <?php else: ?>
                        <td colspan="2">
                            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 js-export-project-row"
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
                        </td>
                    <?php endif; ?>
                </tr>
            </tbody>
        </table>
    </div>
</div>
