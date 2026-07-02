<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-9 col-md-6 col-lg-5">
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-4"><?= $e($t('auth.password_title')) ?></h1>

                <div class="alert alert-danger d-none js-password-error" role="alert"></div>
                <div class="alert alert-success d-none js-password-success" role="alert"></div>

                <form class="js-password-form" action="<?= $e(Url::to('/password')) ?>" method="post" novalidate>
                    <div class="mb-3">
                        <label for="current_password" class="form-label"><?= $e($t('auth.current_password')) ?></label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label"><?= $e($t('auth.new_password')) ?></label>
                        <input type="password" class="form-control" id="new_password" name="new_password"
                               autocomplete="new-password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password_confirm" class="form-label"><?= $e($t('auth.new_password_confirm')) ?></label>
                        <input type="password" class="form-control" id="new_password_confirm" name="new_password_confirm"
                               autocomplete="new-password" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100"><?= $e($t('common.save')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
