<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $interventions */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Real progress: share of this project's interventions already completed.
$ivTotal     = count($interventions);
$ivDone      = 0;
foreach ($interventions as $iv) {
    if ((string) $iv['status'] === 'completed') { $ivDone++; }
}
$ivPercent = $ivTotal > 0 ? (int) round($ivDone / $ivTotal * 100) : 0;

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/sub')) . '">'
    . '<i class="bi bi-arrow-left" aria-hidden="true"></i> ' . $e($t('sub.back_to_list')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => $project['name'],
    'subtitle' => ($project['location'] ?? '') !== '' ? $project['location'] : null,
    'actions'  => $actions,
], null);
?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 mb-0"><?= $e($project['name']) ?></h2>
            <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $project['status']], null) ?>
        </div>
        <dl class="app-dl">
            <?php if (($project['location'] ?? '') !== ''): ?>
                <div class="app-dl-row"><dt><?= $e($t('sub.location')) ?></dt><dd><?= $e($project['location']) ?></dd></div>
            <?php endif; ?>
            <div class="app-dl-row"><dt><?= $e($t('sub.start_date')) ?></dt><dd><?= $e($project['start_date']) ?></dd></div>
            <?php if (($project['end_date'] ?? '') !== ''): ?>
                <div class="app-dl-row"><dt><?= $e($t('sub.end_date')) ?></dt><dd><?= $e($project['end_date']) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ($ivTotal > 0): ?>
            <div class="app-meter mt-3">
                <div class="app-meter-track">
                    <div class="app-meter-fill<?= $ivPercent >= 100 ? ' is-success' : '' ?>" style="width:<?= $e((string) $ivPercent) ?>%"></div>
                </div>
                <span class="app-meter-val"><?= $e((string) $ivPercent) ?>%</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<h2 class="app-section-title"><?= $e($t('sub.interventions')) ?></h2>

<?php if ($interventions === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('sub.no_interventions'),
        'actions' => [],
    ], null) ?>
<?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($interventions as $iv): ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                        <h3 class="h6 mb-1"><?= $e($iv['title']) ?></h3>
                        <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $iv['status']], null) ?>
                    </div>
                    <?php if ($iv['scheduled_date']): ?>
                        <p class="small text-muted mb-2">
                            <i class="bi bi-calendar-event" aria-hidden="true"></i>
                            <?= $e($t('sub.scheduled_date')) ?>: <?= $e($iv['scheduled_date']) ?>
                        </p>
                    <?php endif; ?>

                    <?php if ($iv['gallery'] === []): ?>
                        <p class="small text-muted mb-0"><?= $e($t('sub.no_photos')) ?></p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($iv['gallery'] as $photo): ?>
                                <a href="<?= $e(Url::to('/sub/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                                    <img src="<?= $e(Url::to('/sub/photos/' . $photo['id'] . '/thumb')) ?>" alt="<?= $e(Lang::label('photo_types', $photo['type'])) ?>"
                                         class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
