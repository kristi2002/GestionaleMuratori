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
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.projects.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.projects.subtitle')) ?></p>
    </div>
    <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#project-modal" data-target-modal="#project-modal">
        <?= $e($t('admin.projects.new')) ?>
    </button>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-4">
        <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>" placeholder="<?= $e($t('common.search')) ?>">
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="client_id">
            <option value=""><?= $e($t('admin.projects.client')) ?> — <?= $e($t('common.all')) ?></option>
            <?php foreach ($clients as $c): ?>
                <option value="<?= $e((string) $c['id']) ?>" <?= ((int) $filters['client_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                    <?= $e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="status">
            <option value=""><?= $e($t('admin.projects.status')) ?> — <?= $e($t('common.all')) ?></option>
            <?php foreach ($statuses as $s): ?>
                <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $e(Lang::label('project_status', $s)) ?></option>
            <?php endforeach; ?>
        </select>
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
                    <th><?= $e($t('admin.projects.name')) ?></th>
                    <th><?= $e($t('admin.projects.client')) ?></th>
                    <th><?= $e($t('admin.projects.location')) ?></th>
                    <th><?= $e($t('admin.projects.start_date')) ?></th>
                    <th><?= $e($t('admin.projects.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($projects === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.projects.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td><?= $e($p['name']) ?></td>
                    <td><?= $e($p['client_name']) ?></td>
                    <td><?= $e($p['location']) ?></td>
                    <td><?= $e($p['start_date']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $e(Lang::label('project_status', $p['status'])) ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/report/pdf')) ?>"><?= $e($t('report.pdf')) ?></a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/report/excel')) ?>"><?= $e($t('report.excel')) ?></a>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit"
                                data-bs-toggle="modal" data-bs-target="#project-modal" data-target-modal="#project-modal"
                                data-record='<?= $e(json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/projects/' . $p['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.projects.delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="project-modal" tabindex="-1" data-title-create="<?= $e($t('admin.projects.new')) ?>" data-title-edit="<?= $e($t('admin.projects.edit')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/projects')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.projects.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.projects.client')) ?></label>
                        <select class="form-select" name="client_id" required>
                            <option value="">—</option>
                            <?php foreach ($clients as $c): ?>
                                <option value="<?= $e((string) $c['id']) ?>"><?= $e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.projects.name')) ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.projects.location')) ?></label>
                        <input type="text" class="form-control" name="location">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.projects.start_date')) ?></label>
                            <input type="date" class="form-control" name="start_date" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.projects.end_date')) ?></label>
                            <input type="date" class="form-control" name="end_date">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.projects.invoice_reference')) ?></label>
                        <input type="text" class="form-control" name="invoice_reference">
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.projects.status')) ?></label>
                        <select class="form-select" name="status">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?= $e($s) ?>"><?= $e(Lang::label('project_status', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
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
