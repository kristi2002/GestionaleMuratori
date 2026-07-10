<?php
use App\Support\Lang;
use App\Support\View;

/** @var array|null $user */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$isAdmin = ($user['role'] ?? '') === 'admin';

// Navigation shortcuts: press G, then the key. Kept in sync with the JS handler
// in public/assets/js/app.js and the admin sidebar sections.
$navShortcuts = [
    ['d', 'shortcuts.go_dashboard'],
    ['c', 'shortcuts.go_clients'],
    ['p', 'shortcuts.go_projects'],
    ['i', 'shortcuts.go_interventions'],
    ['q', 'shortcuts.go_quotes'],
    ['f', 'shortcuts.go_invoices'],
    ['s', 'shortcuts.go_expenses'],
    ['m', 'shortcuts.go_warehouse'],
    ['b', 'shortcuts.go_attendance'],
    ['u', 'shortcuts.go_users'],
    ['e', 'shortcuts.go_exports'],
];
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('shortcuts.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('shortcuts.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => $isAdmin ? '/admin' : '/'], null) ?>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><?= $e($t('shortcuts.nav_group')) ?></span>
                <span class="small text-muted fw-normal"><?= $e($t('shortcuts.nav_hint')) ?></span>
            </div>
            <div class="card-body">
                <?php if (!$isAdmin): ?>
                    <p class="text-muted small mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= $e($t('shortcuts.admin_only_note')) ?></p>
                <?php endif; ?>
                <ul class="list-unstyled app-kbd-list mb-0">
                    <?php foreach ($navShortcuts as [$key, $labelKey]): ?>
                        <li class="d-flex align-items-center justify-content-between">
                            <span><?= $e($t($labelKey)) ?></span>
                            <span class="app-kbd-combo">
                                <kbd class="app-kbd">G</kbd>
                                <span class="app-kbd-then"><?= $e($t('shortcuts.then')) ?></span>
                                <kbd class="app-kbd"><?= $e(strtoupper($key)) ?></kbd>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="card h-100">
            <div class="card-header"><?= $e($t('shortcuts.general_group')) ?></div>
            <div class="card-body">
                <ul class="list-unstyled app-kbd-list mb-0">
                    <li class="d-flex align-items-center justify-content-between">
                        <span><?= $e($t('shortcuts.open_guide')) ?></span>
                        <span class="app-kbd-combo"><kbd class="app-kbd">?</kbd></span>
                    </li>
                    <li class="d-flex align-items-center justify-content-between">
                        <span><?= $e($t('shortcuts.focus_search')) ?></span>
                        <span class="app-kbd-combo"><kbd class="app-kbd">/</kbd></span>
                    </li>
                    <li class="d-flex align-items-center justify-content-between">
                        <span><?= $e($t('shortcuts.close')) ?></span>
                        <span class="app-kbd-combo"><kbd class="app-kbd">Esc</kbd></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
