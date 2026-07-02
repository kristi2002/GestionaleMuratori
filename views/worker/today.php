<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $interventions */
/** @var string $tab */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$tab = $tab ?? 'today';
$tabs = [
    'today'    => $t('worker.tab_today'),
    'upcoming' => $t('worker.tab_upcoming'),
    'done'     => $t('worker.tab_done'),
];
$emptyByTab = [
    'today'    => $t('worker.empty_today'),
    'upcoming' => $t('worker.empty_upcoming'),
    'done'     => $t('worker.empty_done'),
];
?>
<h1 class="h4 mb-1"><?= $e($t('worker.today_title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('worker.today_subtitle')) ?></p>

<ul class="nav nav-pills mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $tab === $key ? 'active' : '' ?>"
               href="<?= $e(Url::to('/worker' . ($key === 'today' ? '' : '?tab=' . $key))) ?>"><?= $e($label) ?></a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="alert alert-warning d-none js-offline-queue-banner" role="status"></div>

<?php if ($interventions === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5"><?= $e($emptyByTab[$tab]) ?></div>
    </div>
<?php endif; ?>

<div class="d-flex flex-column gap-2">
    <?php foreach ($interventions as $iv): ?>
        <a class="card text-decoration-none text-reset" href="<?= $e(Url::to('/worker/interventions/' . $iv['id'])) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="h6 mb-1"><?= $e($iv['title']) ?></h2>
                    <span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span>
                </div>
                <p class="small text-muted mb-1"><?= $e($iv['project_name']) ?> — <?= $e($iv['client_name']) ?></p>
                <?php if ($tab === 'done' && $iv['completed_at']): ?>
                    <p class="small text-success mb-0"><?= $e(substr((string) $iv['completed_at'], 0, 16)) ?></p>
                <?php elseif ($tab === 'upcoming' && $iv['scheduled_date']): ?>
                    <p class="small text-success mb-0">
                        <?= $e($iv['scheduled_date']) ?>
                        <?= $iv['scheduled_start_time'] ? ' ' . $e(substr((string) $iv['scheduled_start_time'], 0, 5)) : '' ?>
                    </p>
                <?php elseif ($iv['scheduled_start_time']): ?>
                    <p class="small text-success mb-0"><?= $e(substr((string) $iv['scheduled_start_time'], 0, 5)) ?></p>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
