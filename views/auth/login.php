<?php
use App\Support\Config;
use App\Support\Lang;
use App\Support\View;
use App\Support\Url;

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
// Never advertise the seed logins on a production install.
$showDemo = Config::get('app.env', 'local') !== 'production';
?>
<div class="app-login-split">
    <!-- Left: brand hero (md+ only) -->
    <?= View::render('partials/auth_hero', [], null) ?>

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
                <div id="login-mfa" class="mb-2 d-none">
                    <label for="code" class="form-label"><?= $e($t('auth.mfa_code_label')) ?></label>
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" class="form-control form-control-lg"
                           id="code" name="code" placeholder="123456">
                    <div class="form-text"><?= $e($t('auth.mfa_code_help')) ?></div>
                </div>
                <div class="text-end mb-3">
                    <a class="small app-login-forgot" href="<?= $e(Url::to('/forgot-password')) ?>"><?= $e($t('auth.login_forgot')) ?></a>
                </div>
                <button type="submit" class="btn btn-success btn-lg w-100" id="login-submit"><?= $e($t('auth.login_submit')) ?></button>
            </form>

            <div class="text-center mt-3">
                <a class="small app-login-forgot" href="<?= $e(Url::to('/request')) ?>"><?= $e($t('public.request.title')) ?></a>
            </div>

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
