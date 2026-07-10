<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $document null = create, row = edit */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var array<int,array<string,mixed>> $projects */
/** @var string[] $subjectTypes */
/** @var string[] $docTypes */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $document !== null;
$pageTitle = $isEdit ? $t('admin.compliance.edit') : $t('admin.compliance.new');
$value     = static fn (string $key): string => (string) ($document[$key] ?? '');

// Initial subject visibility: on create default to the first subject type
// (worker) shown/enabled; on edit reflect the stored subject_type. The other
// subject selects stay hidden AND disabled so they are excluded from submit.
$currentSubjectType = $isEdit ? (string) $document['subject_type'] : 'worker';
$currentSubjectId   = $isEdit ? (string) ($document['subject_id'] ?? '') : '';
$currentDocType     = $isEdit ? (string) $document['doc_type'] : '';

$subjectSubtitle = $isEdit
    ? trim(Lang::label('compliance_subject', $currentSubjectType) . ' · ' . Lang::label('compliance_doc', $currentDocType))
    : $t('admin.compliance.subtitle');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($subjectSubtitle) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/compliance'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.compliance.title'), '/admin/compliance'],
    [$isEdit ? $t('admin.compliance.edit') : $t('admin.compliance.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form js-compliance-form"
              data-base-url="<?= $e(Url::to('/admin/compliance')) ?>"
              data-redirect="<?= $e(Url::to('/admin/compliance')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $document['id'] : '') ?>">

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.subject')) ?></label>
                    <select class="form-select js-compliance-subject-type" name="subject_type" required>
                        <?php foreach ($subjectTypes as $st): ?>
                            <option value="<?= $e($st) ?>" <?= $currentSubjectType === $st ? 'selected' : '' ?>><?= $e(Lang::label('compliance_subject', $st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.doc_type')) ?></label>
                    <select class="form-select" name="doc_type" required>
                        <?php foreach ($docTypes as $dt): ?>
                            <option value="<?= $e($dt) ?>" <?= $currentDocType === $dt ? 'selected' : '' ?>><?= $e(Lang::label('compliance_doc', $dt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php $isWorker = $currentSubjectType === 'worker'; ?>
            <div class="mb-3 js-compliance-subject js-compliance-subject-worker <?= $isWorker ? '' : 'd-none' ?>">
                <label class="form-label"><?= $e(Lang::label('compliance_subject', 'worker')) ?></label>
                <select class="form-select" name="subject_id" data-subject="worker" <?= $isWorker ? '' : 'disabled' ?>>
                    <?php foreach ($workers as $w): ?>
                        <option value="<?= $e((string) $w['id']) ?>" <?= $isWorker && $currentSubjectId === (string) $w['id'] ? 'selected' : '' ?>><?= $e($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php $isSub = $currentSubjectType === 'subcontractor'; ?>
            <div class="mb-3 js-compliance-subject js-compliance-subject-subcontractor <?= $isSub ? '' : 'd-none' ?>">
                <label class="form-label"><?= $e(Lang::label('compliance_subject', 'subcontractor')) ?></label>
                <select class="form-select" name="subject_id" data-subject="subcontractor" <?= $isSub ? '' : 'disabled' ?>>
                    <?php foreach ($subcontractors as $s): ?>
                        <option value="<?= $e((string) $s['id']) ?>" <?= $isSub && $currentSubjectId === (string) $s['id'] ? 'selected' : '' ?>><?= $e($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php $isProject = $currentSubjectType === 'project'; ?>
            <div class="mb-3 js-compliance-subject js-compliance-subject-project <?= $isProject ? '' : 'd-none' ?>">
                <label class="form-label"><?= $e(Lang::label('compliance_subject', 'project')) ?></label>
                <select class="form-select" name="subject_id" data-subject="project" <?= $isProject ? '' : 'disabled' ?>>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>" <?= $isProject && $currentSubjectId === (string) $p['id'] ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.issue_date')) ?></label>
                    <input type="date" class="form-control" name="issue_date" value="<?= $e($value('issue_date')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.expiry')) ?></label>
                    <input type="date" class="form-control" name="expiry_date" value="<?= $e($value('expiry_date')) ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.reference')) ?></label>
                    <input type="text" class="form-control" name="reference" value="<?= $e($value('reference')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.compliance.credits')) ?></label>
                    <input type="number" min="0" step="1" class="form-control" name="credits" value="<?= $e($value('credits')) ?>">
                    <div class="form-text"><?= $e($t('admin.compliance.credits_help')) ?></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.compliance.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="2"><?= $e($value('notes')) ?></textarea>
            </div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/compliance')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
