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
    <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#subcontractor-modal" data-target-modal="#subcontractor-modal">
        <?= $e($t('admin.subcontractors.new')) ?>
    </button>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-4">
        <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary"><?= $e($t('common.search')) ?></button>
    </div>
</form>

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
                <?php $record = ['id' => $s['id'], 'name' => $s['name'], 'vat_or_tax_id' => $s['vat_or_tax_id'], 'email' => $s['email'], 'phone' => $s['phone'], 'notes' => $s['notes']]; ?>
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
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit"
                                data-bs-toggle="modal" data-bs-target="#subcontractor-modal" data-target-modal="#subcontractor-modal"
                                data-record='<?= $e(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
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

<div class="modal fade" id="subcontractor-modal" tabindex="-1" data-title-create="<?= $e($t('admin.subcontractors.new')) ?>" data-title-edit="<?= $e($t('admin.subcontractors.edit')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/subcontractors')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.subcontractors.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.subcontractors.name')) ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.subcontractors.vat')) ?></label>
                        <input type="text" class="form-control" name="vat_or_tax_id">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.subcontractors.email')) ?></label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.subcontractors.phone')) ?></label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.subcontractors.notes')) ?></label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $e($t('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
