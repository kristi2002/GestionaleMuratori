<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;
$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="text-center py-5">
    <h1 class="display-6 text-danger">500</h1>
    <p class="lead"><?= $e($t('errors.unexpected')) ?></p>
    <p class="text-muted"><?= $e($t('errors.retry_hint')) ?></p>
    <?php if (!empty($ref)): ?>
        <p class="text-muted small"><?= $e($t('errors.reference')) ?>: <code><?= $e($ref) ?></code></p>
    <?php endif; ?>
    <a class="btn btn-success" href="<?= $e(Url::to('/')) ?>"><?= $e($t('errors.back_home')) ?></a>
</div>
