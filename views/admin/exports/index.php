<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var string $currentMonth  YYYY-MM */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<h1 class="h4 mb-1"><?= $e($t('admin.exports.title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('admin.exports.subtitle')) ?></p>

<div class="card">
    <div class="card-body">
        <h2 class="h6"><?= $e($t('admin.exports.accountant')) ?></h2>
        <p class="small text-muted"><?= $e($t('admin.exports.accountant_help')) ?></p>
        <form method="get" action="<?= $e(Url::to('/admin/exports/accountant')) ?>" class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label"><?= $e($t('admin.exports.month')) ?></label>
                <input type="month" class="form-control" name="month" value="<?= $e($currentMonth) ?>" required>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success"><?= $e($t('admin.exports.download')) ?></button>
            </div>
        </form>
    </div>
</div>
