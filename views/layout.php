<?php
/** @var string $content */
/** @var string $base */
/** @var array|null $user */
/** @var string|null $title */
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
    <title><?= $e($title ?? null) ?><?= isset($title) ? ' — ' : '' ?><?= $e(Lang::get('app_name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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
                <a class="btn btn-sm btn-outline-light" href="<?= $e(Url::to('/logout')) ?>">Esci</a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container py-4">
    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="<?= $e($base) ?>/assets/js/app.js"></script>
</body>
</html>
