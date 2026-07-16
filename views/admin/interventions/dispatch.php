<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var \DateTimeImmutable $from */
/** @var \DateTimeImmutable $to */
/** @var array<int,array<int,array<string,mixed>>> $byWorker */
/** @var array<int,array<string,int>> $dayCount */
/** @var array<int,array<string,mixed>> $workers */
/** @var string $prev */
/** @var string $next */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$workerName = [];
foreach ($workers as $w) {
    $workerName[(int) $w['id']] = (string) $w['name'];
}

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/interventions/calendar')) . '">'
    . '<i class="bi bi-calendar3" aria-hidden="true"></i> ' . $e($t('admin.interventions.calendar_view')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => $t('admin.interventions.dispatch'),
    'subtitle' => sprintf($t('admin.interventions.dispatch_week'), $from->format('d/m/Y'), $to->format('d/m/Y')),
    'actions'  => $actions,
], null);
?>

<div class="d-flex justify-content-between mb-3">
    <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/dispatch?from=' . $prev)) ?>">
        <i class="bi bi-chevron-left" aria-hidden="true"></i> <?= $e($t('admin.interventions.dispatch_prev')) ?>
    </a>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/dispatch?from=' . $next)) ?>">
        <?= $e($t('admin.interventions.dispatch_next')) ?> <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </a>
</div>

<?php if ($byWorker === []): ?>
    <?= View::render('partials/empty_state', ['message' => $t('admin.interventions.dispatch_empty'), 'hint' => '', 'actions' => []], null) ?>
<?php else: ?>
    <?php
    // Assigned workers first (by name), the unassigned bucket (id 0) last.
    $wids = array_keys($byWorker);
    usort($wids, static function ($a, $b) use ($workerName): int {
        if ($a === 0) { return 1; }
        if ($b === 0) { return -1; }
        return strcmp($workerName[$a] ?? '', $workerName[$b] ?? '');
    });
    ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($wids as $wid): $rows = $byWorker[$wid]; ?>
            <div class="card">
                <div class="card-header bg-white d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <i class="bi bi-person-workspace" aria-hidden="true"></i>
                        <?= $e($wid === 0 ? $t('admin.interventions.dispatch_unassigned') : ($workerName[$wid] ?? ('#' . $wid))) ?>
                    </span>
                    <span class="badge text-bg-secondary"><?= $e($t('admin.interventions.dispatch_load')) ?>: <?= count($rows) ?></span>
                </div>
                <div class="list-group list-group-flush">
                    <?php foreach ($rows as $r):
                        $date   = (string) $r['scheduled_date'];
                        $double = $wid !== 0 && ($dayCount[$wid][$date] ?? 0) > 1;
                    ?>
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-2">
                            <span class="text-muted small" style="min-width:8rem">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                <?= $e($date) ?><?= $r['scheduled_start_time'] ? ' ' . $e(substr((string) $r['scheduled_start_time'], 0, 5)) : '' ?>
                            </span>
                            <a class="app-card-title-link flex-grow-1" href="<?= $e(Url::to('/admin/interventions/' . $r['id'])) ?>">
                                <?= $e((string) $r['title']) ?>
                                <span class="text-muted small">— <?= $e((string) $r['project_name']) ?></span>
                            </a>
                            <?php if ($double): ?>
                                <span class="badge text-bg-warning" title="<?= $e($t('admin.interventions.dispatch_double_booked')) ?>">
                                    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= $e($t('admin.interventions.dispatch_double_booked')) ?>
                                </span>
                            <?php endif; ?>
                            <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $r['status']], null) ?>
                            <select class="form-select form-select-sm w-auto js-reassign"
                                    data-url="<?= $e(Url::to('/admin/interventions/' . $r['id'] . '/reassign')) ?>"
                                    aria-label="<?= $e($t('admin.interventions.dispatch_reassign')) ?>">
                                <option value="0"><?= $e($t('admin.interventions.dispatch_unassign')) ?></option>
                                <?php foreach ($workers as $w): ?>
                                    <option value="<?= (int) $w['id'] ?>" <?= (int) $w['id'] === $wid ? 'selected' : '' ?>>
                                        <?= $e((string) $w['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
