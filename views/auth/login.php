<?php
use App\Support\Config;
use App\Support\Lang;
use App\Support\View;
use App\Support\Url;

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
// Never advertise the seed logins on a production install.
$showDemo = Config::get('app.env', 'local') !== 'production';

$features = [
    $t('auth.hero_feature_sites'),
    $t('auth.hero_feature_safety'),
    $t('auth.hero_feature_billing'),
];
?>
<div class="app-login-split">
    <!-- Left: brand hero (md+ only) -->
    <aside class="app-login-hero">
        <div class="app-login-hero-top">
            <span class="app-login-hero-brand">
                <span class="app-login-hero-dot" aria-hidden="true"></span>
                <?= $e($t('app_name')) ?>
            </span>
        </div>
        <div class="app-login-hero-body">
            <h1 class="app-login-hero-title"><?= $e($t('auth.hero_title')) ?></h1>
            <p class="app-login-hero-sub"><?= $e($t('auth.hero_subtitle')) ?></p>
            <ul class="app-login-hero-features">
                <?php foreach ($features as $f): ?>
                    <li><i class="bi bi-check2" aria-hidden="true"></i> <?= $e($f) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="app-login-hero-foot">
            &copy; <?= date('Y') ?> <?= $e($t('app_name')) ?>
        </div>
        <i class="bi bi-buildings app-login-hero-glyph" aria-hidden="true"></i>
    </aside>

    <!-- Right: sign-in form -->
    <div class="app-login-panel">
        <div class="app-login-card">
            <span class="app-brand-chip app-login-mark" aria-hidden="true">GM</span>
            <h2 class="app-login-title"><?= $e($t('auth.login_heading')) ?></h2>
            <p class="app-login-lead"><?= $e($t('auth.login_intro')) ?></p>

            <div id="login-error" class="alert alert-danger d-none" role="alert"></div>

            <form id="login-form" action="<?= $e(Url::to('/login')) ?>" method="post" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label"><?= $e($t('auth.login_email')) ?></label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email"
                           autocomplete="username" required autofocus>
                </div>
                <div class="mb-2">
                    <label for="password" class="form-label"><?= $e($t('auth.login_password')) ?></label>
                    <input type="password" class="form-control form-control-lg" id="password" name="password"
                           autocomplete="current-password" required>
                </div>
                <div class="text-end mb-3">
                    <a class="small app-login-forgot" href="<?= $e(Url::to('/forgot-password')) ?>"><?= $e($t('auth.login_forgot')) ?></a>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100" id="login-submit"><?= $e($t('auth.login_submit')) ?></button>
            </form>

            <?php if ($showDemo): ?>
                <div class="app-login-demo">
                    <strong><?= $e($t('auth.login_demo')) ?></strong>
                    <span>(password: <code>password</code>)</span>
                    <div>admin@gestionale.local · worker1@gestionale.local · client1@gestionale.local</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
