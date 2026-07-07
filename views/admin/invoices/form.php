<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $invoice null = create, row = edit */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,string> $statuses */
/** @var string $suggestedNumber */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $invoice !== null;
$pageTitle = $isEdit ? $t('admin.invoices.edit') : $t('admin.invoices.new');
$value     = static fn (string $key): string => (string) ($invoice[$key] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? $invoice['number'] : $t('admin.invoices.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/invoices', 'label' => $t('admin.invoices.back_to_list')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.invoices.title'), '/admin/invoices'],
    [$isEdit ? (string) $invoice['number'] : $t('admin.invoices.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/invoices')) ?>"
              data-redirect="<?= $e(Url::to('/admin/invoices')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $invoice['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.invoices.section_main')) ?></h2>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.project')) ?></label>
                    <select class="form-select" name="project_id" required>
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>"
                                    <?= $isEdit && (int) $invoice['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_number')) ?></label>
                    <input type="text" class="form-control" name="number"
                           value="<?= $e($isEdit ? (string) $invoice['number'] : $suggestedNumber) ?>"
                           placeholder="<?= $e($t('admin.projects.invoice_number_placeholder')) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-6 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_date')) ?></label>
                    <input type="date" class="form-control" name="issue_date"
                           value="<?= $e($isEdit ? (string) $invoice['issue_date'] : date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_amount')) ?></label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="amount"
                           value="<?= $e($value('amount')) ?>" required>
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_status')) ?></label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $e($s) ?>"
                                    <?= ($isEdit ? $invoice['status'] : 'issued') === $s ? 'selected' : '' ?>>
                                <?= $e(Lang::label('invoice_status', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.projects.invoice_note')) ?></label>
                <input type="text" class="form-control" name="note" value="<?= $e($value('note')) ?>"
                       placeholder="<?= $e($t('admin.projects.invoice_note_placeholder')) ?>">
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
