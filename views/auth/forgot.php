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
<div class="row justify-content-center">
    <div class="col-12 col-sm-9 col-md-6 col-lg-5">
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-3 d-flex align-items-center gap-2">
                    <span class="app-brand-chip">GM</span> <?= $e($t('auth.forgot_title')) ?>
                </h1>

                <?php if ($sent): ?>
                    <div class="alert alert-success"><?= $e($t('auth.forgot_sent')) ?></div>
                    <a class="btn btn-outline-secondary w-100" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('auth.back_to_login')) ?></a>
                <?php else: ?>
                    <p class="text-muted small mb-4"><?= $e($t('auth.forgot_intro')) ?></p>
                    <form action="<?= $e(Url::to('/forgot-password')) ?>" method="post" novalidate>
                        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   autocomplete="username" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-success w-100"><?= $e($t('auth.forgot_submit')) ?></button>
                    </form>
                    <div class="text-center mt-3">
                        <a class="small" href="<?= $e(Url::to('/login')) ?>"><?= $e($t('auth.back_to_login')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
