<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;
$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="row justify-content-center py-5">
    <div class="col-12 col-sm-8 col-md-6 col-lg-5">
        <div class="card text-center">
            <div class="card-body p-4 p-md-5">
                <i class="bi bi-exclamation-octagon display-5 text-danger d-block mb-3" aria-hidden="true"></i>
                <h1 class="h3 mb-2"><?= $e($t('errors.unexpected')) ?></h1>
                <p class="text-muted mb-3"><?= $e($t('errors.retry_hint')) ?></p>
                <?php if (!empty($ref)): ?>
                    <p class="text-muted small mb-4"><?= $e($t('errors.reference')) ?>: <code><?= $e($ref) ?></code></p>
                <?php endif; ?>
                <a class="btn btn-success" href="<?= $e(Url::to('/')) ?>">
                    <i class="bi bi-house" aria-hidden="true"></i> <?= $e($t('errors.back_home')) ?>
                </a>
            </div>
        </div>
    </div>
</div>
