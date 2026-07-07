<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $documents Attached documents, newest first */
/** @var array<int,array<string,mixed>> $invoices Registered invoices, newest first */
/** @var array<int,array<string,mixed>> $projectMaterials Materials logged directly on the project, newest first */
/** @var array<int,array{item_name:string,unit:string,total_qty:string}> $materials Per-item totals (direct logs + interventions) */
/** @var array<int,array<string,mixed>> $warehouseItems Items for the "Aggiungi materiale" picker */
/** @var array<int,string> $invoiceStatuses */
/** @var array<int,array<string,mixed>> $attWorkers Assigned workers (attendance roster) */
/** @var array<int,array<string,mixed>> $attAvailable Active workers not yet assigned ("+ Assegna operaio" picker) */
/** @var array<string,true> $attAbsences Absence set keyed by "<user_id>|<Y-m-d>" */
/** @var \DateTimeImmutable $attMonth First day of the displayed month */
/** @var string $attPrev Previous month as YYYY-MM */
/** @var string $attNext Next month as YYYY-MM */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$projectId = (int) $project['id'];
$dash      = static fn ($v): string => ($v ?? '') !== '' ? (string) $v : '—';
$fmtQty    = static fn (string $qty): string => rtrim(rtrim($qty, '0'), '.');
$fmtBytes  = static function (int $bytes): string {
    return $bytes >= 1048576
        ? number_format($bytes / 1048576, 1, ',', '.') . ' MB'
        : number_format(max(1, (int) round($bytes / 1024)), 0, ',', '.') . ' KB';
};
?>
<div class="mb-2">
    <?= View::render('partials/back_button', ['href' => '/admin/projects', 'label' => $t('nav.projects')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.projects.title'), '/admin/projects'],
    [(string) $project['name'], null],
]], null) ?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="h4 mb-0 text-truncate"><?= $e($project['name']) ?></h1>
                    <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $project['status']], null) ?>
                </div>
                <ul class="list-inline small text-muted mt-1 mb-0 app-profile-meta">
                    <li class="list-inline-item">
                        <i class="bi bi-person" aria-hidden="true"></i>
                        <?= $e($project['client_name']) ?>
                    </li>
                    <li class="list-inline-item">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <?= $e($dash($project['location'])) ?>
                    </li>
                    <li class="list-inline-item">
                        <i class="bi bi-calendar-event" aria-hidden="true"></i>
                        <?= $e((string) $project['start_date']) ?><?= ($project['end_date'] ?? '') !== '' && $project['end_date'] !== null ? ' → ' . $e((string) $project['end_date']) : '' ?>
                    </li>
                    <li class="list-inline-item">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <?= $e(($project['worker_names'] ?? '') !== '' && $project['worker_names'] !== null ? $project['worker_names'] : $t('admin.projects.no_workers')) ?>
                    </li>
                </ul>
            </div>
            <div class="d-flex gap-2 flex-wrap flex-shrink-0">
                <a class="btn btn-success" href="<?= $e(Url::to('/admin/projects/' . $projectId . '/edit')) ?>">
                    <i class="bi bi-pencil" aria-hidden="true"></i> <?= $e($t('common.edit')) ?>
                </a>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/projects/' . $projectId . '/report/pdf')) ?>">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> <?= $e($t('report.pdf')) ?>
                </a>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/projects/' . $projectId . '/report/excel')) ?>">
                    <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i> <?= $e($t('report.excel')) ?>
                </a>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs app-profile-tabs mb-3" role="tablist" data-app-tabs>
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="projTabDocumentsBtn" type="button" role="tab"
                data-bs-toggle="tab" data-bs-target="#documenti"
                aria-controls="documenti" aria-selected="true">
            <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('admin.projects.tab_documents')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="projTabInvoiceBtn" type="button" role="tab"
                data-bs-toggle="tab" data-bs-target="#fattura"
                aria-controls="fattura" aria-selected="false">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= $e($t('admin.projects.tab_invoice')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="projTabMaterialsBtn" type="button" role="tab"
                data-bs-toggle="tab" data-bs-target="#materiali"
                aria-controls="materiali" aria-selected="false">
            <i class="bi bi-box-seam" aria-hidden="true"></i> <?= $e($t('admin.projects.tab_materials')) ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="projTabAttendanceBtn" type="button" role="tab"
                data-bs-toggle="tab" data-bs-target="#presenze"
                aria-controls="presenze" aria-selected="false">
            <i class="bi bi-calendar3" aria-hidden="true"></i> <?= $e($t('admin.projects.tab_attendance')) ?>
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- Documenti -->
    <div class="tab-pane fade show active" id="documenti" role="tabpanel" aria-labelledby="projTabDocumentsBtn">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-cloud-arrow-up text-success" aria-hidden="true"></i>
                <?= $e($t('admin.projects.documents_title')) ?>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3"><?= $e($t('admin.projects.documents_hint')) ?></p>
                <form class="js-upload-form row g-2 align-items-end"
                      data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/documents')) ?>"
                      enctype="multipart/form-data">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="documentTitle"><?= $e($t('admin.projects.document_title')) ?></label>
                        <input type="text" class="form-control" id="documentTitle" name="title" maxlength="150"
                               placeholder="<?= $e($t('admin.projects.document_title_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="documentFile"><?= $e($t('admin.projects.document_file')) ?></label>
                        <input type="file" class="form-control" id="documentFile" name="document" required
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="col-12 col-md-3">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-upload" aria-hidden="true"></i> <?= $e($t('admin.projects.document_upload')) ?>
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-danger py-2 mb-0 d-none js-upload-error" role="alert"></div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($documents === []): ?>
            <div class="card">
                <div class="app-empty-state">
                    <i class="bi bi-folder2-open" aria-hidden="true"></i>
                    <p class="mb-1 fw-semibold"><?= $e($t('admin.projects.documents_empty')) ?></p>
                    <p class="small mb-0"><?= $e($t('admin.projects.documents_hint')) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= $e($t('admin.projects.document_title')) ?></th>
                                <th><?= $e($t('admin.projects.document_file')) ?></th>
                                <th><?= $e($t('admin.projects.document_uploaded_by')) ?></th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($doc['title']) ?></td>
                                    <td class="small">
                                        <?= $e($doc['original_name']) ?>
                                        <span class="text-muted">(<?= $e($fmtBytes((int) $doc['size_bytes'])) ?>)</span>
                                    </td>
                                    <td class="small">
                                        <?= $e($doc['uploaded_by_name']) ?>
                                        <div class="text-muted"><?= $e(date('d/m/Y H:i', (int) strtotime((string) $doc['created_at']))) ?></div>
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <a class="btn btn-sm btn-outline-secondary"
                                           href="<?= $e(Url::to('/admin/projects/' . $projectId . '/documents/' . (int) $doc['id'])) ?>">
                                            <i class="bi bi-download" aria-hidden="true"></i> <?= $e($t('admin.projects.document_download')) ?>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                                data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/documents/' . (int) $doc['id'] . '/delete')) ?>"
                                                data-confirm="<?= $e($t('admin.projects.document_delete_confirm')) ?>">
                                            <?= $e($t('common.delete')) ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Fattura -->
    <div class="tab-pane fade" id="fattura" role="tabpanel" aria-labelledby="projTabInvoiceBtn">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-file-earmark-text text-success" aria-hidden="true"></i>
                <?= $e($t('admin.projects.invoices_title')) ?>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-1"><?= $e($t('admin.projects.invoices_hint')) ?></p>
                <p class="small mb-3">
                    <span class="text-muted"><?= $e($t('admin.projects.invoice_reference_label')) ?>:</span>
                    <span class="fw-semibold"><?= $e($dash($project['invoice_reference'])) ?></span>
                </p>
                <form class="js-crud-form row g-2 align-items-end"
                      data-base-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/invoices')) ?>">
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="invoiceNumber"><?= $e($t('admin.projects.invoice_number')) ?></label>
                        <input type="text" class="form-control" id="invoiceNumber" name="number" maxlength="100" required
                               placeholder="<?= $e($t('admin.projects.invoice_number_placeholder')) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="invoiceDate"><?= $e($t('admin.projects.invoice_date')) ?></label>
                        <input type="date" class="form-control" id="invoiceDate" name="issue_date" required
                               value="<?= $e(date('Y-m-d')) ?>">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="invoiceAmount"><?= $e($t('admin.projects.invoice_amount')) ?></label>
                        <input type="number" class="form-control" id="invoiceAmount" name="amount" min="0.01" step="0.01" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="invoiceStatus"><?= $e($t('admin.projects.invoice_status')) ?></label>
                        <select class="form-select" id="invoiceStatus" name="status">
                            <?php foreach ($invoiceStatuses as $s): ?>
                                <option value="<?= $e($s) ?>" <?= $s === 'issued' ? 'selected' : '' ?>><?= $e(Lang::label('invoice_status', $s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="invoiceNote"><?= $e($t('admin.projects.invoice_note')) ?></label>
                        <input type="text" class="form-control" id="invoiceNote" name="note" maxlength="255"
                               placeholder="<?= $e($t('admin.projects.invoice_note_placeholder')) ?>">
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.projects.invoice_add')) ?>
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-danger py-2 mb-0 d-none js-crud-error" role="alert"></div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($invoices === []): ?>
            <div class="card">
                <div class="app-empty-state">
                    <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                    <p class="mb-1 fw-semibold"><?= $e($t('admin.projects.invoices_empty')) ?></p>
                    <p class="small mb-0"><?= $e($t('admin.projects.invoices_hint')) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= $e($t('admin.projects.invoice_number')) ?></th>
                                <th><?= $e($t('admin.projects.invoice_date')) ?></th>
                                <th class="text-end"><?= $e($t('admin.projects.invoice_amount')) ?></th>
                                <th><?= $e($t('admin.projects.invoice_status')) ?></th>
                                <th><?= $e($t('admin.projects.invoice_note')) ?></th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($inv['number']) ?></td>
                                    <td class="text-nowrap"><?= $e(date('d/m/Y', (int) strtotime((string) $inv['issue_date']))) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= $inv['amount'] !== null ? $e(number_format((float) $inv['amount'], 2, ',', '.')) . ' €' : '—' ?>
                                    </td>
                                    <td>
                                        <?= View::render('partials/status_badge', ['group' => 'invoice_status', 'value' => (string) $inv['status']], null) ?>
                                    </td>
                                    <td class="small"><?= $e($dash($inv['note'])) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                                data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/invoices/' . (int) $inv['id'] . '/delete')) ?>"
                                                data-confirm="<?= $e($t('admin.projects.invoice_delete_confirm')) ?>">
                                            <?= $e($t('common.delete')) ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Materiali: direct logging + per-item totals -->
    <div class="tab-pane fade" id="materiali" role="tabpanel" aria-labelledby="projTabMaterialsBtn">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-box-seam text-success" aria-hidden="true"></i>
                <?= $e($t('admin.projects.materials_title')) ?>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-3"><?= $e($t('admin.projects.material_add_hint')) ?></p>
                <form class="js-crud-form row g-2 align-items-end"
                      data-base-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/materials')) ?>">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="materialItem"><?= $e($t('admin.projects.material_item')) ?></label>
                        <select class="form-select" id="materialItem" name="item_id" required>
                            <option value="">—</option>
                            <?php foreach ($warehouseItems as $wi): ?>
                                <option value="<?= $e((string) $wi['id']) ?>">
                                    <?= $e($wi['name']) ?> (<?= $e($fmtQty((string) $wi['qty_in_stock'])) ?> <?= $e(Lang::label('units', (string) $wi['unit'])) ?> <?= $e($t('admin.warehouse.qty_in_stock')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label" for="materialQty"><?= $e($t('admin.projects.material_qty')) ?></label>
                        <input type="number" class="form-control" id="materialQty" name="qty" step="0.001" min="0.001" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="materialNote"><?= $e($t('admin.projects.material_note')) ?></label>
                        <input type="text" class="form-control" id="materialNote" name="note" maxlength="255"
                               placeholder="<?= $e($t('admin.projects.material_note_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.projects.material_add')) ?>
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="alert alert-danger py-2 mb-0 d-none js-crud-error" role="alert"></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-journal-text text-success" aria-hidden="true"></i>
                <?= $e($t('admin.projects.materials_log_title')) ?>
            </div>
            <?php if ($projectMaterials === []): ?>
                <div class="card-body">
                    <div class="app-empty-state py-4">
                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                        <p class="mb-1 fw-semibold"><?= $e($t('admin.projects.materials_log_empty')) ?></p>
                        <p class="small mb-0"><?= $e($t('admin.projects.material_add_hint')) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= $e($t('admin.projects.material_item')) ?></th>
                                <th class="text-end"><?= $e($t('admin.projects.material_qty')) ?></th>
                                <th><?= $e($t('admin.projects.material_note')) ?></th>
                                <th><?= $e($t('admin.projects.material_logged_by')) ?></th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projectMaterials as $pm): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e($pm['item_name']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= $e($fmtQty((string) $pm['qty'])) ?> <?= $e(Lang::label('units', (string) $pm['unit'])) ?>
                                    </td>
                                    <td class="small"><?= $e($dash($pm['note'])) ?></td>
                                    <td class="small">
                                        <?= $e($pm['created_by_name']) ?>
                                        <div class="text-muted"><?= $e(date('d/m/Y H:i', (int) strtotime((string) $pm['created_at']))) ?></div>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                                data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/materials/' . (int) $pm['id'] . '/delete')) ?>"
                                                data-confirm="<?= $e($t('admin.projects.material_delete_confirm')) ?>">
                                            <?= $e($t('common.delete')) ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="bi bi-list-check text-success" aria-hidden="true"></i>
                <?= $e($t('admin.projects.materials_summary_title')) ?>
            </div>
            <?php if ($materials === []): ?>
                <div class="card-body">
                    <div class="app-empty-state py-4">
                        <i class="bi bi-box-seam" aria-hidden="true"></i>
                        <p class="mb-1 fw-semibold"><?= $e($t('admin.projects.materials_empty')) ?></p>
                        <p class="small mb-0"><?= $e($t('admin.projects.materials_hint')) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body pb-0">
                    <p class="small text-muted mb-0"><?= $e($t('admin.projects.materials_hint')) ?></p>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th><?= $e($t('admin.projects.materials_col_item')) ?></th>
                                <th class="text-end"><?= $e($t('admin.projects.materials_col_qty')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $m): ?>
                                <tr>
                                    <td><?= $e($m['item_name']) ?></td>
                                    <td class="text-end text-nowrap">
                                        <?= $e($fmtQty((string) $m['total_qty'])) ?> <?= $e(Lang::label('units', (string) $m['unit'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Presenze: absence-by-default register, one numbered day strip per worker -->
    <div class="tab-pane fade" id="presenze" role="tabpanel" aria-labelledby="projTabAttendanceBtn">
        <div class="card" data-att-error="<?= $e($t('admin.projects.attendance_error')) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="h6 mb-1 d-flex align-items-center gap-2">
                            <i class="bi bi-calendar3 text-success" aria-hidden="true"></i>
                            <?= $e($t('admin.projects.attendance_title')) ?>
                        </h2>
                        <p class="small text-muted mb-0"><?= $e($t('admin.projects.attendance_hint')) ?></p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a class="app-att-nav-btn" href="<?= $e(Url::to('/admin/projects/' . $projectId . '?att=' . $attPrev)) ?>#presenze"
                           aria-label="<?= $e($t('admin.projects.attendance_prev')) ?>">
                            <i class="bi bi-chevron-left" aria-hidden="true"></i>
                        </a>
                        <span class="app-att-month fw-semibold">
                            <?= $e(Lang::label('months', (string) (int) $attMonth->format('n'))) ?> <?= $e($attMonth->format('Y')) ?>
                        </span>
                        <a class="app-att-nav-btn" href="<?= $e(Url::to('/admin/projects/' . $projectId . '?att=' . $attNext)) ?>#presenze"
                           aria-label="<?= $e($t('admin.projects.attendance_next')) ?>">
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <form class="js-att-assign d-flex align-items-center gap-2"
                          data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/workers')) ?>"
                          data-none-label="<?= $e($t('admin.projects.attendance_assign_none')) ?>"
                          data-placeholder-label="<?= $e($t('admin.projects.attendance_assign_placeholder')) ?>">
                        <select class="form-select js-att-assign-select" aria-label="<?= $e($t('admin.projects.attendance_assign')) ?>"
                                <?= $attAvailable === [] ? 'disabled' : '' ?> style="min-width: 15rem;">
                            <?php if ($attAvailable === []): ?>
                                <option value=""><?= $e($t('admin.projects.attendance_assign_none')) ?></option>
                            <?php else: ?>
                                <option value=""><?= $e($t('admin.projects.attendance_assign_placeholder')) ?></option>
                                <?php foreach ($attAvailable as $w): ?>
                                    <option value="<?= $e((string) $w['id']) ?>"><?= $e($w['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <button type="submit" class="btn btn-success text-nowrap" <?= $attAvailable === [] ? 'disabled' : '' ?>>
                            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.projects.attendance_assign')) ?>
                        </button>
                    </form>
                    <div class="app-att-legend">
                        <?php foreach (['worked', 'absent'] as $st): ?>
                            <span class="app-att-legend-item">
                                <span class="app-att-dot st-<?= $e($st) ?>"></span><?= $e(Lang::label('attendance_status', $st)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="app-empty-state py-4 js-att-empty<?= $attWorkers !== [] ? ' d-none' : '' ?>">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <p class="mb-1 fw-semibold"><?= $e($t('admin.projects.attendance_empty')) ?></p>
                    <p class="small mb-0"><?= $e($t('admin.projects.attendance_empty_hint')) ?></p>
                </div>

                <?php
                $daysInMonth = (int) $attMonth->format('t');
                $monthPrefix = $attMonth->format('Y-m-');
                $today       = date('Y-m-d');
                // Monday-first offsets so day 1 lands under its weekday column.
                $lead     = (int) $attMonth->format('N') - 1;
                $trail    = (7 - (($lead + $daysInMonth) % 7)) % 7;
                $weekdays = array_map(static fn (int $d): string => Lang::label('weekdays_short', (string) $d), range(1, 7));
                ?>
                <div class="row g-3 js-att-register<?= $attWorkers === [] ? ' d-none' : '' ?>"
                     data-days="<?= $daysInMonth ?>" data-lead="<?= $lead ?>"
                     data-month="<?= $e($attMonth->format('Y-m')) ?>" data-month-prefix="<?= $e($monthPrefix) ?>"
                     data-today="<?= $e($today) ?>" data-weekdays="<?= $e(json_encode($weekdays)) ?>"
                     data-toggle-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/attendance')) ?>"
                     data-remove-url-base="<?= $e(Url::to('/admin/projects/' . $projectId . '/workers')) ?>"
                     data-remove-confirm="<?= $e($t('admin.projects.attendance_remove_confirm')) ?>"
                     data-remove-label="<?= $e($t('admin.projects.attendance_remove')) ?>">
                    <div class="col-12 col-md-4 col-xl-3 app-att-workers-col">
                        <div class="app-att-workers js-att-workers">
                            <?php foreach ($attWorkers as $i => $w): $workerId = (int) $w['id']; ?>
                                <div class="app-att-worker-item js-att-worker<?= $i === 0 ? ' active' : '' ?>"
                                     data-worker="<?= $workerId ?>" role="button" tabindex="0">
                                    <span class="app-att-worker-name" title="<?= $e($w['name']) ?>"><?= $e($w['name']) ?></span>
                                    <button type="button" class="app-att-remove js-att-remove"
                                            data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/workers/' . $workerId . '/remove')) ?>"
                                            data-worker="<?= $workerId ?>" data-name="<?= $e($w['name']) ?>"
                                            data-confirm="<?= $e($t('admin.projects.attendance_remove_confirm')) ?>"
                                            title="<?= $e($t('admin.projects.attendance_remove')) ?>"
                                            aria-label="<?= $e($t('admin.projects.attendance_remove')) ?>">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-8 col-xl-9 js-att-panels">
                        <?php foreach ($attWorkers as $i => $w): $workerId = (int) $w['id']; ?>
                            <div class="js-att-panel<?= $i === 0 ? '' : ' d-none' ?>" data-worker="<?= $workerId ?>">
                                <div class="app-att-weekdays" aria-hidden="true">
                                    <?php foreach ($weekdays as $wd): ?><span><?= $e($wd) ?></span><?php endforeach; ?>
                                </div>
                                <div class="app-att-grid">
                                    <?php for ($i2 = 0; $i2 < $lead; $i2++): ?>
                                        <span class="app-att-day is-empty" aria-hidden="true"></span>
                                    <?php endfor; ?>
                                    <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                        <?php
                                        $date   = $monthPrefix . sprintf('%02d', $day);
                                        $absent = isset($attAbsences[$workerId . '|' . $date]);
                                        $label  = Lang::label('attendance_status', $absent ? 'absent' : 'worked');
                                        ?>
                                        <button type="button"
                                                class="app-att-day js-att-day<?= $absent ? ' st-absent' : '' ?><?= $date === $today ? ' is-today' : '' ?>"
                                                data-url="<?= $e(Url::to('/admin/projects/' . $projectId . '/attendance')) ?>"
                                                data-worker="<?= $workerId ?>" data-date="<?= $e($date) ?>"
                                                aria-label="<?= $e($w['name']) ?> — <?= $e(date('d/m/Y', (int) strtotime($date))) ?>: <?= $e($label) ?>">
                                            <?= $day ?>
                                        </button>
                                    <?php endfor; ?>
                                    <?php for ($i2 = 0; $i2 < $trail; $i2++): ?>
                                        <span class="app-att-day is-empty" aria-hidden="true"></span>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
