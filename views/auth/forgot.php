<?php
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var bool $sent */

$e    = static fn (?string $v): string => View::e($v);
$t    = static fn (string $k): string => Lang::get($k);
$sent = $sent ?? false;
?>
<div class="app-login-split">
    <!-- Left: brand hero (md+ only) -->
    <?= View::render('partials/auth_hero', [], null) ?>

    <!-- Right: reset-request form -->
    <div class="app-login-panel">
        <div class="app-login-card">
            <span class="app-brand-chip app-login-mark" aria-hidden="true">GM</span>
            <h2 class="app-login-title"><?= $e($t('auth.forgot_title')) ?></h2>

            <?php if ($sent): ?>
                <div class="alert alert-success"><?= $e($t('auth.forgot_sent')) ?></div>
                <a class="btn btn-outline-secondary btn-lg w-100" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('auth.back_to_login')) ?></a>
            <?php else: ?>
                <p class="app-login-lead"><?= $e($t('auth.forgot_intro')) ?></p>
                <form action="<?= $e(Url::to('/forgot-password')) ?>" method="post" novalidate>
                    <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label"><?= $e($t('auth.login_email')) ?></label>
                        <input type="email" class="form-control form-control-lg" id="email" name="email"
                               autocomplete="username" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-success btn-lg w-100"><?= $e($t('auth.forgot_submit')) ?></button>
                </form>
                <div class="text-center mt-3">
                    <a class="small app-login-forgot" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('auth.back_to_login')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
