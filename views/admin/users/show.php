<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $record */
/** @var array<string,mixed> $profile */
/** @var bool $hasAvatar */
/** @var bool $avatarErr */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$stats    = $profile['stats'];
$worked   = array_flip($profile['attendance']); // 'Y-m-d' => idx, for O(1) lookup
$qtyHours = static fn (float $h): string => rtrim(rtrim(number_format($h, 1, ',', '.'), '0'), ',');

// Tenure in whole years from the hire date, if set.
$tenure = null;
if (($record['hire_date'] ?? null)) {
    try {
        $tenure = (new DateTimeImmutable((string) $record['hire_date']))->diff(new DateTimeImmutable('today'))->y;
    } catch (\Exception $ex) {
        $tenure = null;
    }
}

// Current-month calendar scaffold for the attendance heatmap (Monday-first).
$fullMonths = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
    'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
$firstOfMonth = new DateTimeImmutable('first day of this month');
$daysInMonth  = (int) $firstOfMonth->format('t');
$lead         = (int) $firstOfMonth->format('N') - 1;
$monthLabel   = $fullMonths[(int) $firstOfMonth->format('n')] . ' ' . $firstOfMonth->format('Y');
$today        = date('Y-m-d');
$weekdays     = array_map(static fn (int $d): string => Lang::label('weekdays_short', (string) $d), range(1, 7));

// Document freshness → status pill class + label.
$docStatus = static function (?string $expiry) use ($today, $t): array {
    if ($expiry === null || $expiry === '') {
        return ['app-status-success', $t('admin.users.doc_valid')];
    }
    if ($expiry < $today) {
        return ['app-status-danger', $t('admin.users.doc_expired')];
    }
    $soon = date('Y-m-d', strtotime($today . ' +30 days'));
    if ($expiry <= $soon) {
        return ['app-status-warning', $t('admin.users.doc_expiring')];
    }
    return ['app-status-success', $t('admin.users.doc_valid')];
};
?>
<?php
$showActions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/users/' . $record['id'] . '/edit')) . '">'
    . '<i class="bi bi-pencil" aria-hidden="true"></i> ' . $e($t('admin.users.edit_profile')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin/users'], null);

echo View::render('partials/page_head', [
    'title'    => (string) $record['name'],
    'subtitle' => Lang::label('roles', (string) $record['role']),
    'actions'  => $showActions,
], null);
?>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.users.title'), '/admin/users'],
    [$record['name'], null],
]], null) ?>

<div class="row g-3">
    <!-- Identity card -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <div class="app-avatar-ring<?= (int) $record['is_active'] !== 1 ? ' is-off' : '' ?> mx-auto mb-3">
                    <?php if ($hasAvatar): ?>
                        <img src="<?= $e(Url::to('/admin/users/' . $record['id'] . '/avatar')) ?>" alt="" class="app-avatar-img">
                    <?php else: ?>
                        <span class="app-avatar-initials"><?= $e(View::initials((string) $record['name'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (($record['job_title'] ?? '') !== ''): ?>
                    <span class="badge rounded-pill app-badge-role mb-2"><?= $e($record['job_title']) ?></span>
                <?php endif; ?>

                <div class="text-muted small mb-3">
                    <?php if ((int) $record['is_active'] === 1): ?>
                        <span class="app-status app-status-success"><?= $e($t('admin.users.active')) ?></span>
                    <?php else: ?>
                        <span class="app-status app-status-neutral"><?= $e($t('admin.users.deactivate')) ?></span>
                    <?php endif; ?>
                </div>

                <ul class="list-unstyled app-card-meta text-start small mb-3">
                    <li><i class="bi bi-envelope" aria-hidden="true"></i> <span class="text-truncate"><?= $e($record['email']) ?></span></li>
                    <?php if (($record['phone'] ?? '') !== ''): ?>
                        <li><i class="bi bi-telephone" aria-hidden="true"></i> <span><?= $e($record['phone']) ?></span></li>
                    <?php endif; ?>
                    <?php if (($record['hire_date'] ?? null)): ?>
                        <li><i class="bi bi-calendar-check" aria-hidden="true"></i> <span><?= $e($record['hire_date']) ?></span></li>
                    <?php endif; ?>
                </ul>

                <div class="row g-2 text-center">
                    <?php if ($tenure !== null): ?>
                        <div class="col">
                            <div class="app-mini-stat"><span class="app-mini-val"><?= $e((string) $tenure) ?></span><span class="app-mini-lab"><?= $e($t('admin.users.years_short')) ?></span></div>
                        </div>
                    <?php endif; ?>
                    <div class="col">
                        <div class="app-mini-stat"><span class="app-mini-val"><?= $e((string) $stats['completed']) ?></span><span class="app-mini-lab"><?= $e($t('admin.users.completed_tasks')) ?></span></div>
                    </div>
                    <div class="col">
                        <div class="app-mini-stat"><span class="app-mini-val"><?= $e((string) $stats['assigned']) ?></span><span class="app-mini-lab"><?= $e($t('admin.users.assigned_tasks')) ?></span></div>
                    </div>
                </div>

                <!-- Avatar upload -->
                <?php if ($avatarErr): ?>
                    <div class="alert alert-danger small mt-3 mb-0"><?= $e($t('admin.users.avatar_invalid')) ?></div>
                <?php endif; ?>
                <form class="mt-3" method="post" enctype="multipart/form-data"
                      action="<?= $e(Url::to('/admin/users/' . $record['id'] . '/avatar')) ?>">
                    <input type="hidden" name="_token" value="<?= $e(\App\Support\Csrf::token()) ?>">
                    <div class="input-group input-group-sm">
                        <input type="file" name="avatar" class="form-control" accept="image/png,image/jpeg" required>
                        <button type="submit" class="btn btn-outline-secondary"><?= $e($t('admin.users.change_avatar')) ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right column: metrics + panels -->
    <div class="col-12 col-lg-8">
        <div class="row g-3 mb-3">
            <div class="col-12 col-sm-4">
                <div class="app-metric is-hours">
                    <div class="app-metric-lab"><i class="bi bi-clock-history me-1" aria-hidden="true"></i><?= $e($t('admin.users.hours_month')) ?></div>
                    <div class="app-metric-val"><?= $e($qtyHours((float) $stats['hours_month'])) ?> h</div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="app-metric is-presence">
                    <div class="app-metric-lab"><i class="bi bi-calendar2-check me-1" aria-hidden="true"></i><?= $e($t('admin.users.presences')) ?></div>
                    <div class="app-metric-val"><?= $e((string) $stats['days_month']) ?> gg</div>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <div class="app-metric is-cantiere">
                    <div class="app-metric-lab"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= $e($t('admin.users.current_site')) ?></div>
                    <div class="app-metric-val app-metric-val-sm"><?= $e($stats['current_site'] ?? $t('admin.users.no_site')) ?></div>
                </div>
            </div>
        </div>

        <!-- Attendance heatmap -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= $e($t('admin.users.attendance_history')) ?></span>
                <span class="small text-muted fw-normal"><?= $e($monthLabel) ?></span>
            </div>
            <div class="card-body">
                <div class="app-att-weekdays">
                    <?php foreach ($weekdays as $wd): ?><span><?= $e($wd) ?></span><?php endforeach; ?>
                </div>
                <div class="app-att-grid">
                    <?php for ($i = 0; $i < $lead; $i++): ?>
                        <div class="app-att-day is-empty"></div>
                    <?php endfor; ?>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $date  = $firstOfMonth->format('Y-m') . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                        $isW   = isset($worked[$date]);
                        $cls   = 'app-att-day' . ($isW ? '' : ' st-absent') . ($date === $today ? ' is-today' : '');
                    ?>
                        <div class="<?= $cls ?>"><?= $d ?></div>
                    <?php endfor; ?>
                </div>
                <div class="app-att-legend mt-3">
                    <span class="app-att-legend-item"><span class="app-att-dot st-worked"></span><?= $e($t('admin.users.presences')) ?></span>
                </div>
            </div>
        </div>

        <!-- Assigned interventions -->
        <div class="card mb-3">
            <div class="card-header"><?= $e($t('admin.users.assigned_tasks')) ?></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <tbody>
                    <?php if ($profile['interventions'] === []): ?>
                        <tr><td class="text-center text-muted py-4"><?= $e($t('admin.users.no_tasks')) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($profile['interventions'] as $iv): ?>
                        <tr>
                            <td>
                                <a class="app-card-title-link fw-semibold" href="<?= $e(Url::to('/admin/interventions/' . $iv['id'])) ?>"><?= $e($iv['title']) ?></a>
                                <div class="small text-muted"><?= $e($iv['project_name']) ?></div>
                            </td>
                            <td class="text-muted small d-none d-md-table-cell"><?= $e($iv['scheduled_date'] ?? '—') ?></td>
                            <td class="text-end">
                                <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $iv['status']], null) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Personal documents -->
        <div class="card">
            <div class="card-header"><?= $e($t('admin.users.personal_docs')) ?></div>
            <div class="card-body">
                <?php if ($profile['documents'] === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('admin.users.no_docs')) ?></p>
                <?php else: ?>
                    <div class="app-doc-grid">
                        <?php foreach ($profile['documents'] as $doc):
                            [$pillClass, $pillLabel] = $docStatus($doc['expiry_date'] ?? null); ?>
                            <div class="app-doc-card">
                                <span class="app-doc-ic"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
                                <div class="min-w-0">
                                    <div class="app-doc-name text-truncate"><?= $e(Lang::label('compliance_doc', (string) $doc['doc_type'])) ?></div>
                                    <div class="small text-muted"><?= $e($doc['expiry_date'] ?? '—') ?></div>
                                </div>
                                <span class="badge rounded-pill app-status <?= $e($pillClass) ?> ms-auto"><?= $e($pillLabel) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
