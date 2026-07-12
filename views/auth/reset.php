<?php
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var bool $done @var bool $valid @var string $token @var string|null $error */

$e     = static fn (?string $v): string => View::e($v);
$t     = static fn (string $k): string => Lang::get($k);
$done  = $done ?? false;
$valid = $valid ?? false;
$error = $error ?? null;
$token = $token ?? '';
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-9 col-md-6 col-lg-5">
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-3 d-flex align-items-center gap-2">
                    <span class="app-brand-chip">GM</span> <?= $e($t('auth.reset_title')) ?>
                </h1>

                <?php if ($done): ?>
                    <div class="alert alert-success"><?= $e($t('auth.reset_done')) ?></div>
                    <a class="btn btn-success w-100" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('auth.back_to_login')) ?></a>
                <?php elseif (!$valid): ?>
                    <div class="alert alert-danger"><?= $e($t('auth.reset_invalid')) ?></div>
                    <a class="btn btn-outline-secondary w-100" href="<?= $e(Url::to('/forgot-password')) ?>"><?= $e($t('auth.forgot_title')) ?></a>
                <?php else: ?>
                    <p class="text-muted small mb-4"><?= $e($t('auth.reset_intro')) ?></p>
                    <?php if ($error !== null): ?>
                        <div class="alert alert-danger"><?= $e($error) ?></div>
                    <?php endif; ?>
                    <form action="<?= $e(Url::to('/reset-password')) ?>" method="post" novalidate>
                        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                        <input type="hidden" name="token" value="<?= $e($token) ?>">
                        <div class="mb-3">
                            <label for="new_password" class="form-label"><?= $e($t('auth.new_password')) ?></label>
                            <input type="password" class="form-control" id="new_password" name="new_password"
                                   autocomplete="new-password" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="new_password_confirm" class="form-label"><?= $e($t('auth.new_password_confirm')) ?></label>
                            <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm"
                                   autocomplete="new-password" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><?= $e($t('auth.reset_submit')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
