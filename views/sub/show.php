<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $interventions */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<a href="<?= $e(Url::to('/sub')) ?>" class="d-inline-block mb-3 small">&larr; <?= $e($t('sub.back_to_list')) ?></a>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <h1 class="h5 mb-1"><?= $e($project['name']) ?></h1>
            <span class="badge text-bg-light border"><?= $e(Lang::label('project_status', $project['status'])) ?></span>
        </div>
        <?php if ($project['location']): ?>
            <p class="small text-muted mb-2"><?= $e($t('sub.location')) ?>: <?= $e($project['location']) ?></p>
        <?php endif; ?>
        <p class="small text-muted mb-0">
            <?= $e($t('sub.start_date')) ?>: <?= $e($project['start_date']) ?>
            <?php if ($project['end_date']): ?>
                — <?= $e($t('sub.end_date')) ?>: <?= $e($project['end_date']) ?>
            <?php endif; ?>
        </p>
    </div>
</div>

<h2 class="h6 mb-2"><?= $e($t('sub.interventions')) ?></h2>

<?php if ($interventions === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-4"><?= $e($t('sub.no_interventions')) ?></div>
    </div>
<?php endif; ?>

<div class="d-flex flex-column gap-3">
    <?php foreach ($interventions as $iv): ?>
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h3 class="h6 mb-1"><?= $e($iv['title']) ?></h3>
                    <span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span>
                </div>
                <p class="small text-muted mb-2">
                    <?php if ($iv['scheduled_date']): ?>
                        <?= $e($t('sub.scheduled_date')) ?>: <?= $e($iv['scheduled_date']) ?>
                    <?php endif; ?>
                </p>

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
