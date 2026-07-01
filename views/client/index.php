<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<h1 class="h4 mb-1"><?= $e($t('client.projects_title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('client.projects_subtitle')) ?></p>

<?php if ($projects === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5"><?= $e($t('client.empty_projects')) ?></div>
    </div>
<?php endif; ?>

<div class="d-flex flex-column gap-2">
    <?php foreach ($projects as $p): ?>
        <a class="card text-decoration-none text-reset" href="<?= $e(Url::to('/client/projects/' . $p['id'])) ?>">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h2 class="h6 mb-1"><?= $e($p['name']) ?></h2>
                    <span class="badge text-bg-light border"><?= $e(Lang::label('project_status', $p['status'])) ?></span>
                </div>
                <?php if ($p['location']): ?>
                    <p class="small text-muted mb-0"><?= $e($p['location']) ?></p>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
