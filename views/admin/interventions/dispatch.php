<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var \DateTimeImmutable $from */
/** @var \DateTimeImmutable $to */
/** @var array<int,array{date:string,weekday:string,day:string,today:bool}> $days */
/** @var array<int,array<string,array<int,array<string,mixed>>>> $byCell */
/** @var array<int,array<string,mixed>> $unscheduled */
/** @var array<int,array<string,mixed>> $workers */
/** @var string $prev */
/** @var string $next */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// One draggable intervention card. Carries its own schedule endpoint + current
// (worker,date) so the drop handler can post the target cell's (worker,date).
$card = static function (array $r) use ($e): string {
    $time = $r['scheduled_start_time'] ? ' · ' . substr((string) $r['scheduled_start_time'], 0, 5) : '';
    return '<div class="gm-board-card js-board-card" draggable="true"'
        . ' data-id="' . $e((string) $r['id']) . '"'
        . ' data-worker="' . (int) ($r['assigned_worker_id'] ?? 0) . '"'
        . ' data-date="' . $e((string) ($r['scheduled_date'] ?? '')) . '"'
        . ' data-url="' . $e(Url::to('/admin/interventions/' . $r['id'] . '/schedule')) . '">'
        . '<div class="gm-board-card-title">' . $e((string) $r['title']) . '</div>'
        . '<div class="gm-board-card-sub">' . $e((string) $r['project_name']) . $e($time) . '</div>'
        . '</div>';
};

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/interventions/calendar')) . '">'
    . '<i class="bi bi-calendar3" aria-hidden="true"></i> ' . $e($t('admin.interventions.calendar_view')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => $t('admin.interventions.dispatch'),
    'subtitle' => sprintf($t('admin.interventions.dispatch_week'), $from->format('d/m/Y'), $to->format('d/m/Y')),
    'actions'  => $actions,
], null);
?>

<div class="d-flex justify-content-between align-items-center gap-2 mb-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/dispatch?from=' . $prev)) ?>">
        <i class="bi bi-chevron-left" aria-hidden="true"></i> <?= $e($t('admin.interventions.dispatch_prev')) ?>
    </a>
    <form method="get" action="<?= $e(Url::to('/admin/interventions/dispatch')) ?>" class="m-0">
        <input type="date" name="from" value="<?= $e($from->format('Y-m-d')) ?>"
               class="form-control form-control-sm w-auto js-auto-submit"
               aria-label="<?= $e($t('admin.interventions.dispatch_jump')) ?>">
    </form>
    <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/dispatch?from=' . $next)) ?>">
        <?= $e($t('admin.interventions.dispatch_next')) ?> <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </a>
</div>

<p class="small text-muted mb-3"><i class="bi bi-hand-index" aria-hidden="true"></i> <?= $e($t('admin.interventions.board_hint')) ?></p>

<!-- To-schedule bucket -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold"><i class="bi bi-inbox" aria-hidden="true"></i> <?= $e($t('admin.interventions.board_unscheduled')) ?></span>
        <span class="badge text-bg-secondary"><?= count($unscheduled) ?></span>
    </div>
    <div class="card-body gm-board-bucket js-board-cell" data-worker="" data-date="">
        <?php if ($unscheduled === []): ?>
            <span class="text-muted small"><?= $e($t('admin.interventions.board_bucket_empty')) ?></span>
        <?php endif; ?>
        <?php foreach ($unscheduled as $r) {
            echo $card($r);
        } ?>
    </div>
</div>

<!-- Worker × day board -->
<div class="gm-board">
    <table class="gm-board-grid">
        <thead>
            <tr>
                <th class="gm-board-corner"></th>
                <?php foreach ($days as $day): ?>
                    <th class="<?= $day['today'] ? 'is-today' : '' ?>">
                        <?= $e($day['weekday']) ?><br><span class="fw-normal"><?= $e($day['day']) ?></span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            // Assigned workers first (list order = by name), unassigned row last.
            $rowWorkers = $workers;
            $rowWorkers[] = ['id' => 0, 'name' => $t('admin.interventions.dispatch_unassigned')];
            foreach ($rowWorkers as $w):
                $wid = (int) $w['id'];
            ?>
                <tr>
                    <th class="gm-board-rowhead"><?= $e((string) $w['name']) ?></th>
                    <?php foreach ($days as $day): $cell = $byCell[$wid][$day['date']] ?? []; ?>
                        <td class="gm-board-cell js-board-cell <?= ($wid !== 0 && count($cell) > 1) ? 'is-overbooked' : '' ?>"
                            data-worker="<?= $wid ?>" data-date="<?= $e($day['date']) ?>">
                            <?php foreach ($cell as $r) { echo $card($r); } ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
