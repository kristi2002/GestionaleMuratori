<?php
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var bool $sent */
/** @var ?string $error */
/** @var array<string,string> $old */

$e   = static fn (?string $v): string => View::e($v);
$t   = static fn (string $k): string => Lang::get($k);
$old = $old ?? [];
$val = static fn (string $k): string => (string) ($old[$k] ?? '');
?>
<div class="app-login-split">
    <?= View::render('partials/auth_hero', [], null) ?>

    <div class="app-login-panel">
        <div class="app-login-card">
            <span class="app-brand-chip app-login-mark" aria-hidden="true">GM</span>
            <h2 class="app-login-title"><?= $e($t('public.request.title')) ?></h2>

            <?php if ($sent): ?>
                <div class="alert alert-success"><?= $e($t('public.request.sent')) ?></div>
                <a class="btn btn-outline-secondary btn-lg w-100" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('public.request.back')) ?></a>
            <?php else: ?>
                <p class="app-login-lead"><?= $e($t('public.request.intro')) ?></p>
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger"><?= $e($error) ?></div>
                <?php endif; ?>
                <form action="<?= $e(Url::to('/request')) ?>" method="post" novalidate>
                    <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                    <!-- Honeypot: hidden from users; bots that fill it are silently dropped. -->
                    <div style="position:absolute;left:-9999px;" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label"><?= $e($t('public.request.name')) ?></label>
                        <input type="text" class="form-control form-control-lg" id="name" name="name" maxlength="190"
                               value="<?= $e($val('name')) ?>" required autofocus>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label for="email" class="form-label"><?= $e($t('public.request.email')) ?></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= $e($val('email')) ?>">
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label for="phone" class="form-label"><?= $e($t('public.request.phone')) ?></label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?= $e($val('phone')) ?>">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="message" class="form-label"><?= $e($t('public.request.message')) ?></label>
                        <textarea class="form-control" id="message" name="message" rows="4" maxlength="2000"><?= $e($val('message')) ?></textarea>
                    </div>
                    <div class="form-text mb-3"><?= $e($t('public.request.contact_hint')) ?></div>
                    <button type="submit" class="btn btn-success btn-lg w-100"><?= $e($t('public.request.submit')) ?></button>
                </form>
                <div class="text-center mt-3">
                    <a class="small app-login-forgot" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('public.request.have_account')) ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
