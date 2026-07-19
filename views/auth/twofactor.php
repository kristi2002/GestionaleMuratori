<?php
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var bool $enabled */
/** @var ?string $error */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $k): string => Lang::get($k);
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-6">
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-1"><?= $e($t('auth.mfa_title')) ?></h1>
                <p class="text-muted"><?= $e($t('auth.mfa_subtitle')) ?></p>

                <?php if ($error !== null): ?>
                    <div class="alert alert-danger"><?= $e($error) ?></div>
                <?php endif; ?>

                <?php if (!empty($recoveryCodes)): ?>
                    <div class="alert alert-warning">
                        <strong><?= $e($t('auth.mfa_recovery_title')) ?></strong>
                        <p class="small mb-2"><?= $e($t('auth.mfa_recovery_help')) ?></p>
                        <div class="row row-cols-2 g-1 font-monospace">
                            <?php foreach ($recoveryCodes as $c): ?>
                                <div class="col"><?= $e($c) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($enabled): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-shield-check" aria-hidden="true"></i> <?= $e($t('auth.mfa_enabled_msg')) ?>
                    </div>
                    <p class="small text-muted"><?= $e(sprintf($t('auth.mfa_recovery_left'), (int) ($recoveryCount ?? 0))) ?></p>
                    <hr>
                    <h2 class="h6"><?= $e($t('auth.mfa_disable')) ?></h2>
                    <form action="<?= $e(Url::to('/2fa/disable')) ?>" method="post" novalidate>
                        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label"><?= $e($t('auth.mfa_confirm_password')) ?></label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn-outline-danger"><?= $e($t('auth.mfa_disable')) ?></button>
                    </form>
                <?php else: ?>
                    <ol class="small ps-3 mb-3">
                        <li><?= $e($t('auth.mfa_step1')) ?></li>
                        <li><?= $e($t('auth.mfa_step2')) ?></li>
                        <li><?= $e($t('auth.mfa_step3')) ?></li>
                    </ol>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('auth.mfa_secret_label')) ?></label>
                        <input type="text" class="form-control font-monospace" value="<?= $e($secretGrouped) ?>" readonly>
                        <div class="form-text"><a href="<?= $e($otpauth) ?>"><?= $e($t('auth.mfa_otpauth_link')) ?></a></div>
                    </div>
                    <form action="<?= $e(Url::to('/2fa/enable')) ?>" method="post" novalidate>
                        <input type="hidden" name="_token" value="<?= $e(Csrf::token()) ?>">
                        <div class="mb-3">
                            <label for="code" class="form-label"><?= $e($t('auth.mfa_code_label')) ?></label>
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" class="form-control"
                                   id="code" name="code" placeholder="123456" required>
                        </div>
                        <button type="submit" class="btn btn-success"><?= $e($t('auth.mfa_enable')) ?></button>
                    </form>
                <?php endif; ?>

                <div class="mt-3"><a class="small" href="<?= $e(Url::to('/password')) ?>"><?= $e($t('auth.mfa_back')) ?></a></div>
            </div>
        </div>
    </div>
</div>
