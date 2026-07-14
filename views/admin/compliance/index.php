<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $documents */
/** @var array{expired:int,exp30:int,exp90:int,valid:int} $buckets */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var array<int,array<string,mixed>> $projects */
/** @var string[] $subjectTypes */
/** @var string[] $docTypes */
/** @var array{subject_type:string,doc_type:string,expiring:bool} $filters */
/** @var string $today */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$todayTs = strtotime($today);
$soon    = (new DateTimeImmutable($today))->modify('+30 days')->format('Y-m-d');

$expiryClass = static function (?string $expiry) use ($today, $soon): string {
    if ($expiry === null) {
        return '';
    }
    if ($expiry < $today) {
        return 'text-danger fw-bold';
    }
    return $expiry <= $soon ? 'text-warning fw-bold' : '';
};

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/compliance/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.compliance.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.compliance.title'),
    'subtitle' => $t('admin.compliance.subtitle'),
    'actions'  => $actions,
], null);

$needsAction = $buckets['expired'] > 0 || $buckets['exp30'] > 0 || $buckets['exp90'] > 0;
?>

<?php if ($needsAction): ?>
    <div class="app-banner-glow d-flex align-items-center gap-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning" aria-hidden="true"></i>
        <span class="fw-semibold">
            <?= $e(sprintf($t('admin.compliance.action_required'), $buckets['expired'], $buckets['exp30'], $buckets['exp90'])) ?>
        </span>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php
    $kpis = [
        ['alert',   'bi-exclamation-octagon', $t('admin.compliance.kpi_expired'),     $buckets['expired']],
        ['warn',    'bi-clock-history',       $t('admin.compliance.kpi_expiring_30'), $buckets['exp30']],
        ['is-info', 'bi-hourglass-split',     $t('admin.compliance.kpi_expiring_90'), $buckets['exp90']],
        ['ok',      'bi-shield-check',        $t('admin.compliance.kpi_valid'),       $buckets['valid']],
    ];
    foreach ($kpis as [$variant, $icon, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="card gm-kpi h-100 <?= $e($variant) ?>">
                <div class="card-body">
                    <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                    <div class="gm-kpi-val mt-2"><?= $e((string) $val) ?></div>
                    <div class="gm-kpi-lab"><?= $e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-selects">
            <select class="form-select" name="subject_type" aria-label="<?= $e($t('admin.compliance.subject')) ?>">
                <option value=""><?= $e($t('common.all')) ?></option>
                <?php foreach ($subjectTypes as $st): ?>
                    <option value="<?= $e($st) ?>" <?= $filters['subject_type'] === $st ? 'selected' : '' ?>><?= $e(Lang::label('compliance_subject', $st)) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="doc_type" aria-label="<?= $e($t('admin.compliance.doc_type')) ?>">
                <option value=""><?= $e($t('common.all')) ?></option>
                <?php foreach ($docTypes as $dt): ?>
                    <option value="<?= $e($dt) ?>" <?= $filters['doc_type'] === $dt ? 'selected' : '' ?>><?= $e(Lang::label('compliance_doc', $dt)) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="form-check app-filter-check">
                <input class="form-check-input" type="checkbox" name="expiring" value="1" id="f-expiring" <?= $filters['expiring'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="f-expiring"><?= $e($t('admin.compliance.filter_expiring')) ?></label>
            </div>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['subject_type'] !== '' || $filters['doc_type'] !== '' || !empty($filters['expiring']),
                'href'   => '/admin/compliance',
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
                    <th><?= $e($t('admin.compliance.subject')) ?></th>
                    <th><?= $e($t('admin.compliance.doc_type')) ?></th>
                    <th><?= $e($t('admin.compliance.reference')) ?></th>
                    <th><?= $e($t('admin.compliance.expiry')) ?></th>
                    <th style="min-width:9rem"><?= $e($t('admin.compliance.days_left')) ?></th>
                    <th><?= $e($t('admin.compliance.status')) ?></th>
                    <th><?= $e($t('admin.compliance.credits')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= $e($t('admin.compliance.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($documents as $d): ?>
                <?php
                $expiry   = $d['expiry_date'];
                $daysLeft = $expiry !== null ? (int) floor((strtotime($expiry) - $todayTs) / 86400) : null;

                // Severity + status: expired < today, expiring within 30 days, else valid.
                $sev = '';
                $statusKey = 'valid';
                if ($expiry !== null) {
                    if ($expiry < $today) {
                        $sev = 'sev-bad';
                        $statusKey = 'expired';
                    } elseif ($expiry <= $soon) {
                        $sev = 'sev-warn';
                        $statusKey = 'expiring';
                    }
                }
                $statusTone = ['expired' => 'danger', 'expiring' => 'warning', 'valid' => 'success'][$statusKey];

                // Days-remaining meter: urgency over a 90-day window (fuller = sooner);
                // overdue clamps to a full danger bar.
                $meterPct   = 0;
                $meterClass = '';
                if ($daysLeft !== null) {
                    $rem      = max(0, min(90, $daysLeft));
                    $meterPct = (int) round((90 - $rem) / 90 * 100);
                    if ($daysLeft < 0) {
                        $meterPct   = 100;
                        $meterClass = ' is-danger';
                    }
                }
                ?>
                <tr class="<?= $e($sev) ?>">
                    <td>
                        <span class="badge text-bg-light border"><?= $e(Lang::label('compliance_subject', $d['subject_type'])) ?></span>
                        <?= $e($d['subject_name'] ?? ($d['subject_type'] === 'company' ? $t('admin.compliance.the_company') : '—')) ?>
                    </td>
                    <td><?= $e(Lang::label('compliance_doc', $d['doc_type'])) ?></td>
                    <td><?= $e($d['reference'] ?? '—') ?></td>
                    <td class="mono tnum <?= $e($expiryClass($expiry)) ?>"><?= $e($expiry ?? '—') ?></td>
                    <td>
                        <?php if ($daysLeft !== null): ?>
                            <div class="app-meter">
                                <div class="app-meter-track">
                                    <div class="app-meter-fill<?= $meterClass ?>" style="width:<?= $e((string) $meterPct) ?>%"></div>
                                </div>
                                <span class="app-meter-val"><?= $e((string) $daysLeft) ?></span>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge rounded-pill app-status app-status-<?= $e($statusTone) ?>">
                            <?= $e($t('admin.compliance.' . $statusKey)) ?>
                        </span>
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
