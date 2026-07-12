<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $subcontractors  each with a 'project_ids' int[] */
/** @var array<int,array<string,mixed>> $projects */
/** @var string $search */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.subcontractors.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.subcontractors.subtitle')) ?></p>
    </div>
    <a class="btn btn-success" href="<?= $e(Url::to('/admin/subcontractors/create')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.subcontractors.new')) ?>
    </a>
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
                    <th><?= $e($t('admin.subcontractors.active')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($subcontractors === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.subcontractors.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($subcontractors as $s): ?>
                <tr class="<?= ((int) $s['is_active']) === 1 ? '' : 'table-secondary text-muted' ?>">
                    <td><?= $e($s['name']) ?></td>
                    <td><?= $e($s['vat_or_tax_id']) ?></td>
                    <td><?= $e($s['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $e((string) count($s['project_ids'])) ?></span></td>
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
