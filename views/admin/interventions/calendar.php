<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var \DateTimeImmutable $month */
/** @var array<string,array<int,array<string,mixed>>> $byDate  date => interventions */
/** @var string $prev */
/** @var string $next */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$daysInMonth = (int) $month->format('t');
$lead        = (int) $month->format('N') - 1; // Monday-first leading blanks
$today       = date('Y-m-d');
$weekdays    = array_map(static fn (int $d): string => Lang::label('weekdays_short', (string) $d), range(1, 7));
$statusColor = ['pending' => '#94A3B8', 'in_progress' => '#3B82F6', 'on_hold' => '#F59E0B', 'completed' => '#10B981', 'cancelled' => '#EF4444'];

// Month jump dropdown: 12 months back to 6 forward around the displayed month.
$currentMonth = $month->format('Y-m');
$monthOptions = [];
$cursor       = $month->modify('-12 months');
for ($k = 0; $k <= 18; $k++) {
    $monthOptions[] = [
        'value' => $cursor->format('Y-m'),
        'label' => Lang::label('months', (string) (int) $cursor->format('n')) . ' ' . $cursor->format('Y'),
    ];
    $cursor = $cursor->modify('+1 month');
}
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.interventions.calendar')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.interventions.subtitle')) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/interventions')) ?>">
            <i class="bi bi-list-ul" aria-hidden="true"></i> <?= $e($t('admin.interventions.list_view')) ?>
        </a>
        <a class="btn btn-success btn-sm" href="<?= $e(Url::to('/admin/interventions/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.interventions.new')) ?>
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="get" action="<?= $e(Url::to('/admin/interventions/calendar')) ?>" class="d-flex align-items-center justify-content-center gap-2 mb-3">
            <a class="app-att-nav-btn" href="<?= $e(Url::to('/admin/interventions/calendar?month=' . $prev)) ?>" aria-label="<?= $e($t('admin.projects.attendance_prev')) ?>">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
            </a>
            <select name="month" class="form-select form-select-sm app-cal-month-select js-auto-submit" aria-label="<?= $e($t('admin.interventions.calendar')) ?>">
                <?php foreach ($monthOptions as $opt): ?>
                    <option value="<?= $e($opt['value']) ?>"<?= $opt['value'] === $currentMonth ? ' selected' : '' ?>><?= $e($opt['label']) ?></option>
                <?php endforeach; ?>
            </select>
            <a class="app-att-nav-btn" href="<?= $e(Url::to('/admin/interventions/calendar?month=' . $next)) ?>" aria-label="<?= $e($t('admin.projects.attendance_next')) ?>">
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        </form>

        <div class="app-cal-weekdays" aria-hidden="true">
            <?php foreach ($weekdays as $wd): ?><span><?= $e($wd) ?></span><?php endforeach; ?>
        </div>
        <div class="app-cal-grid">
            <?php for ($i = 0; $i < $lead; $i++): ?>
                <div class="app-cal-day is-empty" aria-hidden="true"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $daysInMonth; $d++):
                $date   = $month->format('Y-m-') . sprintf('%02d', $d);
                $events = $byDate[$date] ?? [];
            ?>
                <div class="app-cal-day<?= $date === $today ? ' is-today' : '' ?>">
                    <div class="app-cal-daynum"><?= $d ?></div>
                    <?php foreach (array_slice($events, 0, 4) as $ev): ?>
                        <a href="<?= $e(Url::to('/admin/interventions/' . $ev['id'])) ?>" class="app-cal-event"
                           title="<?= $e($ev['title'] . ' — ' . $ev['project_name']) ?>"
                           style="border-left-color: <?= $e($statusColor[$ev['status']] ?? '#adb5bd') ?>">
                            <?php if (($ev['scheduled_start_time'] ?? null)): ?><span class="app-cal-time"><?= $e(substr((string) $ev['scheduled_start_time'], 0, 5)) ?></span> <?php endif; ?><?= $e($ev['title']) ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if (count($events) > 4): ?>
                        <div class="app-cal-more">+<?= count($events) - 4 ?></div>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div>
