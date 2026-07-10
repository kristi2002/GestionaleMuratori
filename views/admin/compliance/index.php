<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $documents */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var array<int,array<string,mixed>> $projects */
/** @var string[] $subjectTypes */
/** @var string[] $docTypes */
/** @var array{subject_type:string,doc_type:string,expiring:bool} $filters */
/** @var string $today */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$soon = (new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');
$expiryClass = static function (?string $expiry) use ($today, $soon): string {
    if ($expiry === null) {
        return '';
    }
    if ($expiry < $today) {
        return 'text-danger fw-bold';
    }
    return $expiry <= $soon ? 'text-warning fw-bold' : '';
};
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.compliance.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.compliance.subtitle')) ?></p>
    </div>
    <a class="btn btn-success" href="<?= $e(Url::to('/admin/compliance/create')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.compliance.new')) ?>
    </a>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-6 col-sm-3">
        <select class="form-select" name="subject_type">
            <option value=""><?= $e($t('common.all')) ?></option>
            <?php foreach ($subjectTypes as $st): ?>
                <option value="<?= $e($st) ?>" <?= $filters['subject_type'] === $st ? 'selected' : '' ?>><?= $e(Lang::label('compliance_subject', $st)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="doc_type">
            <option value=""><?= $e($t('common.all')) ?></option>
            <?php foreach ($docTypes as $dt): ?>
                <option value="<?= $e($dt) ?>" <?= $filters['doc_type'] === $dt ? 'selected' : '' ?>><?= $e(Lang::label('compliance_doc', $dt)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto d-flex align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="expiring" value="1" id="f-expiring" <?= $filters['expiring'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="f-expiring"><?= $e($t('admin.compliance.filter_expiring')) ?></label>
        </div>
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
                    <th><?= $e($t('admin.compliance.subject')) ?></th>
                    <th><?= $e($t('admin.compliance.doc_type')) ?></th>
                    <th><?= $e($t('admin.compliance.reference')) ?></th>
                    <th><?= $e($t('admin.compliance.expiry')) ?></th>
                    <th><?= $e($t('admin.compliance.credits')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.compliance.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($documents as $d): ?>
                <?php $sev = '';
                if ($d['expiry_date'] !== null) {
                    $sev = $d['expiry_date'] < $today ? 'sev-bad' : ($d['expiry_date'] <= $soon ? 'sev-warn' : '');
                }
                ?>
                <tr class="<?= $e($sev) ?>">
                    <td>
                        <span class="badge text-bg-light border"><?= $e(Lang::label('compliance_subject', $d['subject_type'])) ?></span>
                        <?= $e($d['subject_name'] ?? ($d['subject_type'] === 'company' ? $t('admin.compliance.the_company') : '—')) ?>
                    </td>
                    <td><?= $e(Lang::label('compliance_doc', $d['doc_type'])) ?></td>
                    <td><?= $e($d['reference'] ?? '—') ?></td>
                    <td class="mono tnum <?= $e($expiryClass($d['expiry_date'])) ?>">
                        <?= $e($d['expiry_date'] ?? '—') ?>
                        <?php if ($d['expiry_date'] !== null && $d['expiry_date'] < $today): ?>
                            <span class="badge text-bg-danger"><?= $e($t('admin.compliance.expired')) ?></span>
                        <?php elseif ($d['expiry_date'] !== null && $d['expiry_date'] <= $soon): ?>
                            <span class="badge text-bg-warning"><?= $e($t('admin.compliance.expiring')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="mono tnum"><?= $e($d['credits'] !== null ? (string) $d['credits'] : '—') ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/compliance/' . $d['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/compliance/' . $d['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.compliance.delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
