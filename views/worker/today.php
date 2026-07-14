<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $interventions */
/** @var string $tab */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$tab = $tab ?? 'today';
$emptyByTab = [
    'today'    => $t('worker.empty_today'),
    'upcoming' => $t('worker.empty_upcoming'),
    'done'     => $t('worker.empty_done'),
];

echo View::render('partials/page_head', [
    'title'    => $t('worker.today_title'),
    'subtitle' => $t('worker.today_subtitle'),
], null);

// Tabs (Oggi / Prossimi / Completati) as design-system pill filters.
echo View::render('partials/filter_pills', ['pills' => [
    ['label' => $t('worker.tab_today'),    'href' => '/worker',              'active' => $tab === 'today'],
    ['label' => $t('worker.tab_upcoming'), 'href' => '/worker?tab=upcoming', 'active' => $tab === 'upcoming'],
    ['label' => $t('worker.tab_done'),     'href' => '/worker?tab=done',     'active' => $tab === 'done'],
]], null);
?>

<div class="alert alert-warning d-none js-offline-queue-banner" role="status"></div>

<?php if ($interventions === []): ?>
    <div class="card">
        <div class="app-empty-state">
            <i class="bi bi-clipboard-check" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold"><?= $e($emptyByTab[$tab]) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($interventions as $iv): ?>
            <a class="card app-record-card text-decoration-none text-reset"
               href="<?= $e(Url::to('/worker/interventions/' . $iv['id'])) ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <h2 class="h6 mb-0"><?= $e($iv['title']) ?></h2>
                        <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $iv['status']], null) ?>
                    </div>
                    <p class="small text-muted mb-2 text-truncate">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <?= $e($iv['project_name']) ?> — <?= $e($iv['client_name']) ?>
                    </p>
                    <?php
                    $timeLine = null;
                    if ($tab === 'done' && $iv['completed_at']) {
                        $timeLine = ['bi-check2-circle', substr((string) $iv['completed_at'], 0, 16)];
                    } elseif ($tab === 'upcoming' && $iv['scheduled_date']) {
                        $timeLine = ['bi-calendar-event', trim($iv['scheduled_date']
                            . ($iv['scheduled_start_time'] ? ' ' . substr((string) $iv['scheduled_start_time'], 0, 5) : ''))];
                    } elseif ($iv['scheduled_start_time']) {
                        $timeLine = ['bi-clock', substr((string) $iv['scheduled_start_time'], 0, 5)];
                    }
                    ?>
                    <?php if ($timeLine !== null): ?>
                        <p class="small mb-0 app-card-meta">
                            <i class="bi <?= $e($timeLine[0]) ?>" aria-hidden="true"></i> <?= $e($timeLine[1]) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
