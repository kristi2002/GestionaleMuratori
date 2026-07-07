<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $project null = create, row = edit */
/** @var array<int,array<string,mixed>> $clients */
/** @var array<int,array<string,mixed>> $workers Active workers selectable for this project */
/** @var array<int,int> $assignedWorkerIds */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $project !== null;
$pageTitle = $isEdit ? $t('admin.projects.edit') : $t('admin.projects.new');
$value     = static fn (string $key): string => (string) ($project[$key] ?? '');

// Selected client: the record's on edit, an optional ?client_id= preselection on create.
$selectedClientId = $isEdit ? (int) $project['client_id'] : (int) ($preselectedClientId ?? 0);
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? $project['name'] : $t('admin.projects.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/projects', 'label' => $t('admin.projects.back_to_list')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.projects.title'), '/admin/projects'],
    [$isEdit ? (string) $project['name'] : $t('admin.projects.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/projects')) ?>"
              data-redirect="<?= $e(Url::to('/admin/projects')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $project['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.projects.section_main')) ?></h2>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.client')) ?></label>
                    <select class="form-select" name="client_id" required>
                        <option value="">—</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $e((string) $c['id']) ?>" <?= $selectedClientId === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= $e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.name')) ?></label>
                    <input type="text" class="form-control" name="name" value="<?= $e($value('name')) ?>"
                           placeholder="<?= $e($t('admin.projects.name_placeholder')) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.projects.location')) ?></label>
                <input type="text" class="form-control" name="location" value="<?= $e($value('location')) ?>"
                       placeholder="<?= $e($t('admin.projects.location_placeholder')) ?>">
            </div>

            <h2 class="app-form-section"><?= $e($t('admin.projects.section_dates')) ?></h2>
            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.start_date')) ?></label>
                    <input type="date" class="form-control" name="start_date" value="<?= $e($value('start_date')) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.end_date')) ?></label>
                    <input type="date" class="form-control" name="end_date" value="<?= $e($value('end_date')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_reference')) ?></label>
                    <input type="text" class="form-control" name="invoice_reference" value="<?= $e($value('invoice_reference')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.status')) ?></label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $e($s) ?>" <?= $isEdit && $project['status'] === $s ? 'selected' : '' ?>>
                                <?= $e(Lang::label('project_status', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <hr>
            <div class="mb-3">
                <label class="form-label mb-1"><?= $e($t('admin.projects.workers')) ?></label>
                <p class="small text-muted mb-2"><?= $e($t('admin.projects.workers_help')) ?></p>
                <?php if ($workers === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('admin.projects.no_workers_available')) ?></p>
                <?php else: ?>
                    <div class="row g-2 app-worker-picker">
                        <?php foreach ($workers as $w): ?>
                            <div class="col-12 col-sm-6 col-lg-4">
                                <label class="form-check app-worker-option">
                                    <input class="form-check-input" type="checkbox" name="worker_ids[]"
                                           value="<?= $e((string) $w['id']) ?>"
                                           <?= in_array((int) $w['id'], $assignedWorkerIds, true) ? 'checked' : '' ?>>
                                    <span class="form-check-label">
                                        <i class="bi bi-tools" aria-hidden="true"></i> <?= $e($w['name']) ?>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/projects')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
