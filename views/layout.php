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
$t = static fn (string $k): string => Lang::get($k);

// Theme is persisted in a cookie and rendered server-side so there is no flash
// of the wrong theme on load (inline <script> is blocked by our CSP).
$theme = (($_COOKIE['gm_theme'] ?? 'light') === 'dark') ? 'dark' : 'light';
$themeColor = $theme === 'dark' ? '#0B0D11' : '#1E232B';

$isAdmin = ($user['role'] ?? null) === 'admin';

// Current path (base-stripped) for sidebar active state.
$path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');
$active = static function (string $href) use ($path): string {
    $on = $href === '/admin' ? ($path === '/admin' || $path === '/') : str_starts_with($path, $href);
    return $on ? ' active' : '';
};

// Admin navigation: [group-label-key, [ [href, label-key, icon-id], ... ]].
$nav = [
    [null, [
        ['/admin', 'admin.nav.dashboard', 'i-grid'],
    ]],
    ['admin.nav.grp_registry', [
        ['/admin/clients', 'admin.clients.title', 'i-users'],
        ['/admin/projects', 'admin.projects.title', 'i-building'],
        ['/admin/subcontractors', 'admin.subcontractors.title', 'i-link'],
    ]],
    ['admin.nav.grp_site', [
        ['/admin/interventions', 'admin.interventions.title', 'i-clipboard'],
        ['/admin/attendance', 'admin.attendance.title', 'i-badge'],
        ['/admin/daily-logs', 'admin.daily_logs.title', 'i-journal'],
        ['/admin/sal', 'admin.sal.title', 'i-file'],
    ]],
    ['admin.nav.grp_safety', [
        ['/admin/compliance', 'admin.compliance.title', 'i-shield'],
        ['/admin/warehouse', 'admin.warehouse.title', 'i-box'],
    ]],
    ['admin.nav.grp_system', [
        ['/admin/exports', 'admin.exports.title', 'i-download'],
        ['/admin/users', 'admin.users.title', 'i-sliders'],
    ]],
];
?>
<!DOCTYPE html>
<html lang="it" data-bs-theme="<?= $e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= $e(Csrf::token()) ?>">
    <meta name="theme-color" content="<?= $e($themeColor) ?>">
    <title><?= $e($title ?? null) ?><?= isset($title) ? ' — ' : '' ?><?= $e(Lang::get('app_name')) ?></title>
    <link rel="manifest" href="<?= $e($base) ?>/manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= $e($base) ?>/assets/icons/icon-192.png">
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="<?= $e($base) ?>/assets/fonts/inter-latin-600-normal.woff2">
    <link href="<?= $e($base) ?>/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $e($base) ?>/assets/css/app.css" rel="stylesheet">
</head>
<body data-base="<?= $e($base) ?>">
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
        <symbol id="i-grid" viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1.2"/><rect x="14" y="4" width="6" height="6" rx="1.2"/><rect x="4" y="14" width="6" height="6" rx="1.2"/><rect x="14" y="14" width="6" height="6" rx="1.2"/></symbol>
        <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><path d="M3.5 20c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M16 5.5a3 3 0 0 1 0 5.5"/><path d="M20.5 20c0-2.5-1.5-4.4-3.6-4.9"/></symbol>
        <symbol id="i-building" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="16" rx="1.2"/><path d="M9 8h2M13 8h2M9 12h2M13 12h2M10 20v-3h4v3"/></symbol>
        <symbol id="i-link" viewBox="0 0 24 24"><path d="M9.5 13a3.2 3.2 0 0 1 0-4.5l2-2a3.2 3.2 0 0 1 4.5 4.5l-1 1"/><path d="M14.5 11a3.2 3.2 0 0 1 0 4.5l-2 2a3.2 3.2 0 0 1-4.5-4.5l1-1"/></symbol>
        <symbol id="i-clipboard" viewBox="0 0 24 24"><rect x="6" y="5" width="12" height="16" rx="2"/><path d="M9 5V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v1"/><path d="M9 11h6M9 15h4"/></symbol>
        <symbol id="i-badge" viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="14" rx="2"/><circle cx="12" cy="11" r="2.2"/><path d="M8.5 17c.6-1.6 2-2.4 3.5-2.4s2.9.8 3.5 2.4"/></symbol>
        <symbol id="i-journal" viewBox="0 0 24 24"><rect x="5" y="4" width="14" height="16" rx="1.5"/><path d="M9 4v16M12 8h4M12 12h4"/></symbol>
        <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 3.5H8a1.5 1.5 0 0 0-1.5 1.5v14A1.5 1.5 0 0 0 8 20.5h8a1.5 1.5 0 0 0 1.5-1.5V7z"/><path d="M14 3.5V7h3.5M9.5 13h5M9.5 16h5"/></symbol>
        <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/></symbol>
        <symbol id="i-box" viewBox="0 0 24 24"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9z"/><path d="M4.2 7.6L12 12l7.8-4.4M12 12v9"/></symbol>
        <symbol id="i-download" viewBox="0 0 24 24"><path d="M12 4v10M8 11l4 4 4-4"/><path d="M5 19h14"/></symbol>
        <symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 8h8M16 8h4M4 16h4M12 16h8"/><circle cx="14" cy="8" r="2.2"/><circle cx="9" cy="16" r="2.2"/></symbol>
        <symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.4 1.4M17.6 17.6L19 19M19 5l-1.4 1.4M6.4 17.6L5 19"/></symbol>
        <symbol id="i-moon" viewBox="0 0 24 24"><path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/></symbol>
        <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>
        <symbol id="i-logout" viewBox="0 0 24 24"><path d="M14 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4"/><path d="M9 12h10M16 9l3 3-3 3"/></symbol>
        <symbol id="i-key" viewBox="0 0 24 24"><circle cx="8" cy="12" r="3.5"/><path d="M11.5 12H20l-2 2M16 12v3"/></symbol>
    </defs>
</svg>

<div class="app-shell<?= $isAdmin ? ' has-sidebar' : '' ?>">
    <?php if ($isAdmin): ?>
        <aside class="app-sidebar" id="app-sidebar">
            <a class="sb-brand" href="<?= $e(Url::to('/admin')) ?>">
                <span class="app-brand-chip">GM</span> <?= $e(Lang::get('app_name')) ?>
            </a>
            <?php foreach ($nav as [$group, $links]): ?>
                <?php if ($group !== null): ?><div class="sb-group"><?= $e($t($group)) ?></div><?php endif; ?>
                <?php foreach ($links as [$href, $labelKey, $icon]): ?>
                    <a class="sb-link<?= $active($href) ?>" href="<?= $e(Url::to($href)) ?>">
                        <svg class="ic" aria-hidden="true"><use href="#<?= $e($icon) ?>"></use></svg>
                        <span><?= $e($t($labelKey)) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </aside>
        <div class="sb-backdrop js-sidebar-close" aria-hidden="true"></div>
    <?php endif; ?>

    <header class="app-topbar navbar navbar-dark">
        <div class="container-fluid gap-2">
            <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-sm btn-icon js-sidebar-toggle"
                        aria-label="<?= $e($t('admin.nav.menu')) ?>">
                    <svg class="ic" width="18" height="18" aria-hidden="true"><use href="#i-menu"></use></svg>
                </button>
            <?php endif; ?>
            <a class="navbar-brand me-auto" href="<?= $e(Url::to('/')) ?>">
                <span class="app-brand-chip">GM</span>
                <span class="d-none d-sm-inline"><?= $e(Lang::get('app_name')) ?></span>
            </a>
            <?php if ($user !== null): ?>
                <span class="badge rounded-pill role"><?= $e(Lang::label('roles', $user['role'])) ?></span>
                <span class="small text-white d-none d-md-inline"><?= $e($user['name']) ?></span>
                <button type="button" class="btn btn-sm btn-icon js-theme-toggle"
                        aria-label="<?= $e($t('admin.nav.toggle_theme')) ?>" title="<?= $e($t('admin.nav.toggle_theme')) ?>">
                    <svg class="ic ic-moon" width="17" height="17" aria-hidden="true"><use href="#i-moon"></use></svg>
                    <svg class="ic ic-sun" width="17" height="17" aria-hidden="true"><use href="#i-sun"></use></svg>
                </button>
                <?php if (in_array($user['role'], ['worker', 'subcontractor'], true)): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= $e(Url::to('/attendance')) ?>"><?= $e(Lang::get('attendance.nav')) ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-icon" href="<?= $e(Url::to('/password')) ?>" title="<?= $e(Lang::get('auth.change_password')) ?>"
                   aria-label="<?= $e(Lang::get('auth.change_password')) ?>">
                    <svg class="ic" width="17" height="17" aria-hidden="true"><use href="#i-key"></use></svg>
                </a>
                <button type="button" class="btn btn-sm btn-icon js-logout"
                        data-url="<?= $e(Url::to('/logout')) ?>" title="<?= $e(Lang::get('auth.logout')) ?>"
                        aria-label="<?= $e(Lang::get('auth.logout')) ?>">
                    <svg class="ic" width="17" height="17" aria-hidden="true"><use href="#i-logout"></use></svg>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <main class="app-content">
        <div class="container py-4">
            <?= $content ?>
        </div>
    </main>
</div>

<script src="<?= $e($base) ?>/assets/vendor/jquery.min.js"></script>
<script src="<?= $e($base) ?>/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= $e($base) ?>/assets/js/app.js"></script>
</body>
</html>
