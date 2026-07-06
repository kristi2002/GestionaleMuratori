<?php
/** @var string $content */
/** @var string $base */
/** @var array|null $user */
/** @var string|null $title */
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\View;
use App\Support\Url;

$base = $base ?? '';
$user = $user ?? null;
$e = static fn (?string $v): string => View::e($v);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $e(Csrf::token()) ?>">
    <meta name="theme-color" content="#b22222">
    <title><?= $e($title ?? null) ?><?= isset($title) ? ' — ' : '' ?><?= $e(Lang::get('app_name')) ?></title>
    <link rel="manifest" href="<?= $e($base) ?>/manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= $e($base) ?>/assets/icons/icon-192.png">
    <link href="<?= $e($base) ?>/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $e($base) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body data-base="<?= $e($base) ?>">
<nav class="navbar navbar-dark app-navbar">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= $e(Url::to('/')) ?>"><?= $e(Lang::get('app_name')) ?></a>
        <?php if ($user !== null): ?>
            <div class="d-flex align-items-center gap-2 text-white">
                <span class="badge rounded-pill text-bg-light"><?= $e(Lang::label('roles', $user['role'])) ?></span>
                <span class="small d-none d-sm-inline"><?= $e($user['name']) ?></span>
                <?php if (in_array($user['role'], ['worker', 'subcontractor'], true)): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= $e(Url::to('/attendance')) ?>"><?= $e(Lang::get('attendance.nav')) ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-light" href="<?= $e(Url::to('/password')) ?>"><?= $e(Lang::get('auth.change_password')) ?></a>
                <button type="button" class="btn btn-sm btn-outline-light js-logout"
                        data-url="<?= $e(Url::to('/logout')) ?>"><?= $e(Lang::get('auth.logout')) ?></button>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container py-4">
    <?= $content ?>
</main>

<script src="<?= $e($base) ?>/assets/vendor/jquery.min.js"></script>
<script src="<?= $e($base) ?>/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= $e($base) ?>/assets/js/app.js"></script>
</body>
</html>
