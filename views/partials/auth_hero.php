<?php
use App\Support\Lang;
use App\Support\View;

// Shared brand hero for the public auth pages (login / forgot / reset), shown on
// the left of the app-login-split at md+ and hidden on small screens via CSS.
$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$features = [
    $t('auth.hero_feature_sites'),
    $t('auth.hero_feature_safety'),
    $t('auth.hero_feature_billing'),
];
?>
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
