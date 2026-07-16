<?php
/** @var string $content */
/** @var string $base */
/** @var array|null $user */
/** @var string|null $title */
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Shortcuts;
use App\Support\View;
use App\Support\Url;

$base = $base ?? '';
$user = $user ?? null;
// Effective key => href map for the admin nav shortcuts, read by app.js.
$shortcutMap = ($user['role'] ?? '') === 'admin'
    ? json_encode(Shortcuts::keyHrefMap($user['shortcuts'] ?? null), JSON_UNESCAPED_SLASHES)
    : '';
$e = static fn (?string $v): string => View::e($v);

// Theme is persisted in a cookie and rendered server-side so there is no flash
// of the wrong theme on load (an inline <script> would be blocked by our CSP).
// The Navy+Orange identity is a dark shell, so dark is the default look.
$theme      = (($_COOKIE['gm_theme'] ?? 'dark') === 'light') ? 'light' : 'dark';
$themeColor = '#080D1A';

// Global sidebar menu, role-aware: [label, href|null, bootstrap-icon, children?].
// A null href marks a section not available yet (muted placeholder). Children are
// [label, href, status?] pairs rendered as an expandable sub-list under the item.
$menu = [];
if ($user !== null) {
    $menu = match ($user['role'] ?? '') {
        'admin' => [
            [Lang::get('admin.nav.dashboard'),        '/admin',               'bi-grid-1x2'],
            [Lang::get('admin.statistics.title'),     '/admin/statistics',    'bi-bar-chart-line'],
            [Lang::get('admin.financials.title'),     '/admin/financials',    'bi-graph-up-arrow'],
            [Lang::get('admin.clients.title'),        '/admin/clients',       'bi-people'],
            [Lang::get('admin.projects.title'),       '/admin/projects',      'bi-buildings'],
            [Lang::get('admin.subcontractors.title'), '/admin/subcontractors','bi-diagram-3'],
            [Lang::get('admin.quotes.title'),         '/admin/quotes',        'bi-file-earmark-text'],
            [Lang::get('admin.suppliers.title'),      '/admin/suppliers',     'bi-truck'],
            [Lang::get('admin.purchase_orders.title'),'/admin/purchase-orders','bi-receipt-cutoff'],
            [Lang::get('admin.invoices.title'),       '/admin/invoices',      'bi-receipt'],
            // One entry per expense category: the list page pre-filtered.
            [Lang::get('admin.expenses.title'),       '/admin/expenses',      'bi-cash-coin',
                array_map(
                    static fn (string $c): array => [Lang::label('expense_categories', $c), '/admin/expenses?category=' . $c],
                    ['meals', 'fuel', 'vehicle', 'clothing', 'other'],
                )],
            [Lang::get('admin.interventions.title'),  '/admin/interventions', 'bi-calendar-week',
                // One entry per workflow status: the list page pre-filtered.
                array_map(
                    static fn (string $s): array => [Lang::label('intervention_status', $s), '/admin/interventions?status=' . $s, $s],
                    ['in_progress', 'on_hold', 'pending', 'completed'],
                )],
            [Lang::get('admin.attendance.title'),     '/admin/attendance',    'bi-person-badge'],
            [Lang::get('admin.daily_logs.title'),     '/admin/daily-logs',    'bi-journal-text'],
            [Lang::get('admin.sal.title'),            '/admin/sal',           'bi-file-earmark-ruled'],
            [Lang::get('admin.compliance.title'),     '/admin/compliance',    'bi-shield-check'],
            [Lang::get('admin.warehouse.title'),      '/admin/warehouse',     'bi-box-seam'],
            [Lang::get('admin.exports.title'),        '/admin/exports',       'bi-download'],
            [Lang::get('admin.users.title'),          '/admin/users',         'bi-person-gear'],
            [Lang::get('admin.audit.title'),          '/admin/audit',         'bi-clock-history'],
        ],
        'worker' => [
            [Lang::get('nav.my_tasks'),    '/worker',      'bi-list-check'],
        ],
        'client' => [
            [Lang::get('nav.my_projects'), '/client',        'bi-folder2-open'],
            [Lang::get('client.quotes.title'), '/client/quotes', 'bi-file-earmark-text'],
        ],
        default => [],
    };
}
$hasSidebar = $menu !== [];

// Active item = the menu entry whose href is the longest prefix of the current path.
$reqPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($base !== '' && str_starts_with($reqPath, $base)) {
    $reqPath = substr($reqPath, strlen($base));
}
$reqPath     = '/' . ltrim($reqPath, '/');
$activeIndex = null;
$activeLen   = -1;
foreach ($menu as $i => $item) {
    $href = $item[1];
    if ($href === null) {
        continue;
    }
    if (($reqPath === $href || str_starts_with($reqPath, $href . '/')) && strlen($href) > $activeLen) {
        $activeIndex = $i;
        $activeLen   = strlen($href);
    }
}

// A sub-link is active when its path matches and every query pair it carries
// is present in the current request (extra request params are ignored).
$subActive = static function (string $href) use ($reqPath): bool {
    if ($reqPath !== (string) parse_url($href, PHP_URL_PATH)) {
        return false;
    }
    parse_str((string) parse_url($href, PHP_URL_QUERY), $query);
    foreach ($query as $key => $value) {
        if ((string) ($_GET[$key] ?? '') !== (string) $value) {
            return false;
        }
    }
    return true;
};

// --- Mobile bottom navigation (shown < lg) -------------------------------
// A curated, role-aware subset of the menu for thumb reach, plus a raised
// quick-create FAB for admins. The persistent sidebar takes over at lg+.
$bottomNav = match ($user['role'] ?? '') {
    'admin' => [
        ['/admin',            Lang::get('nav.home'),              'bi-house-door'],
        ['/admin/projects',   Lang::get('admin.projects.title'),  'bi-buildings'],
        ['/admin/compliance', Lang::get('nav.safety'),            'bi-shield-check'],
        ['/admin/warehouse',  Lang::get('admin.warehouse.title'), 'bi-box-seam'],
    ],
    'worker' => [
        ['/worker',     Lang::get('nav.tasks'),      'bi-list-check'],
        ['/attendance', Lang::get('attendance.nav'), 'bi-person-badge'],
    ],
    'client' => [
        ['/client',        Lang::get('nav.projects'), 'bi-folder2-open'],
        ['/client/quotes', Lang::get('nav.quotes'),   'bi-file-earmark-text'],
    ],
    default => [],
};
$hasBottomNav = $bottomNav !== [];
$showFab      = ($user['role'] ?? '') === 'admin';

// Active bottom item = longest href prefix of the current path. Section roots
// (/admin, /client, /worker) match only exactly, so they aren't "active" on
// every sub-page of the app.
$bottomActive    = -1;
$bottomActiveLen = -1;
$roots           = ['/admin', '/client', '/worker'];
foreach ($bottomNav as $bi => $bItem) {
    $bh    = $bItem[0];
    $match = ($reqPath === $bh)
        || (!in_array($bh, $roots, true) && str_starts_with($reqPath, $bh . '/'));
    if ($match && strlen($bh) > $bottomActiveLen) {
        $bottomActive    = $bi;
        $bottomActiveLen = strlen($bh);
    }
}

// Quick-create links for the admin FAB action sheet.
$fabActions = [
    ['/admin/projects/create',      Lang::get('admin.projects.new'),      'bi-buildings'],
    ['/admin/interventions/create', Lang::get('admin.interventions.new'), 'bi-calendar-plus'],
    ['/admin/quotes/create',        Lang::get('admin.quotes.new'),        'bi-file-earmark-text'],
    ['/admin/purchase-orders/create', Lang::get('admin.purchase_orders.new'), 'bi-receipt-cutoff'],
    ['/admin/invoices/create',      Lang::get('admin.invoices.new'),      'bi-receipt'],
    ['/admin/expenses/create',      Lang::get('admin.expenses.new'),      'bi-cash-coin'],
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
    <link rel="icon" type="image/svg+xml" href="<?= $e($base) ?>/assets/img/favicon.svg">
    <link rel="manifest" href="<?= $e($base) ?>/manifest.webmanifest">
    <link rel="apple-touch-icon" href="<?= $e($base) ?>/assets/icons/icon-192.png">
    <link rel="preload" as="font" type="font/woff2" crossorigin
          href="<?= $e($base) ?>/assets/fonts/inter-latin-600-normal.woff2">
    <?php // Local assets only (no CDN): the app must work offline too. ?>
    <link href="<?= $e($base) ?>/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $e($base) ?>/assets/vendor/bootstrap-icons.min.css" rel="stylesheet">
    <?php // filemtime cache-buster: browsers drop stale copies whenever the file changes. ?>
    <link href="<?= $e($base) ?>/assets/css/app.css?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= $hasBottomNav ? 'app-has-bottom-nav' : '' ?>" data-base="<?= $e($base) ?>" data-role="<?= $e($user['role'] ?? '') ?>" data-shortcuts="<?= $e($shortcutMap) ?>">
<nav class="navbar navbar-dark app-navbar sticky-top">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <?php if ($hasSidebar): ?>
                <button class="navbar-toggler d-lg-none me-2" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar"
                        aria-label="<?= $e(Lang::get('nav.open_menu')) ?>">
                    <span class="navbar-toggler-icon"></span>
                </button>
            <?php endif; ?>
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-white" href="<?= $e(Url::to('/')) ?>">
                <span class="app-brand-chip">GM</span>
                <span class="d-none d-sm-inline"><?= $e(Lang::get('app_name')) ?></span>
            </a>
        </div>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
            <form class="d-none d-md-flex flex-grow-1 justify-content-center px-3" method="get" action="<?= $e(Url::to('/admin/search')) ?>" role="search">
                <div class="input-group input-group-sm app-navbar-search">
                    <span class="input-group-text bg-white border-0"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" class="form-control border-0" name="q"
                           placeholder="<?= $e(Lang::get('admin.search.placeholder')) ?>" aria-label="<?= $e(Lang::get('admin.search.title')) ?>">
                </div>
            </form>
        <?php endif; ?>
        <?php if ($user !== null): ?>
            <div class="d-flex align-items-center gap-2">
                <span class="badge rounded-pill role"><?= $e(Lang::label('roles', $user['role'])) ?></span>
                <span class="small text-white d-none d-md-inline"><?= $e($user['name']) ?></span>
                <?php if (($user['role'] ?? '') === 'admin'): $nu = $notifUnread ?? 0; ?>
                    <a class="btn btn-sm btn-icon position-relative" href="<?= $e(Url::to('/admin/notifications')) ?>"
                       title="<?= $e(Lang::get('nav.notifications')) ?>" aria-label="<?= $e(Lang::get('nav.notifications')) ?>">
                        <i class="bi bi-bell" aria-hidden="true"></i>
                        <?php if ($nu > 0): ?>
                            <span class="app-notif-badge badge rounded-pill text-bg-danger"><?= $e($nu > 99 ? '99+' : (string) $nu) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-icon js-theme-toggle"
                        aria-label="<?= $e(Lang::get('admin.nav.toggle_theme')) ?>" title="<?= $e(Lang::get('admin.nav.toggle_theme')) ?>">
                    <i class="bi bi-moon-stars ic-moon" aria-hidden="true"></i>
                    <i class="bi bi-sun ic-sun" aria-hidden="true"></i>
                </button>
                <?php if (in_array($user['role'], ['worker', 'subcontractor'], true)): ?>
                    <a class="btn btn-sm btn-outline-light" href="<?= $e(Url::to('/attendance')) ?>"><?= $e(Lang::get('attendance.nav')) ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-icon d-none d-md-inline-grid" href="<?= $e(Url::to('/shortcuts')) ?>"
                   title="<?= $e(Lang::get('shortcuts.title')) ?>" aria-label="<?= $e(Lang::get('shortcuts.title')) ?>">
                    <i class="bi bi-keyboard" aria-hidden="true"></i>
                </a>
                <a class="btn btn-sm btn-icon" href="<?= $e(Url::to('/password')) ?>"
                   title="<?= $e(Lang::get('auth.change_password')) ?>" aria-label="<?= $e(Lang::get('auth.change_password')) ?>">
                    <i class="bi bi-key" aria-hidden="true"></i>
                </a>
                <button type="button" class="btn btn-sm btn-icon js-logout"
                        data-url="<?= $e(Url::to('/logout')) ?>" title="<?= $e(Lang::get('auth.logout')) ?>"
                        aria-label="<?= $e(Lang::get('auth.logout')) ?>">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                </button>
            </div>
        <?php endif; ?>
    </div>
</nav>

<div class="app-shell">
    <?php if ($hasSidebar): ?>
        <aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="appSidebar"
               aria-label="<?= $e(Lang::get('nav.menu')) ?>">
            <div class="offcanvas-header d-lg-none">
                <h2 class="offcanvas-title h5"><?= $e(Lang::get('nav.menu')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar"
                        aria-label="<?= $e(Lang::get('nav.close_menu')) ?>"></button>
            </div>
            <div class="offcanvas-body app-sidebar-body">
                <nav class="nav flex-column app-sidebar-nav">
                    <?php foreach ($menu as $i => $item): ?>
                        <?php [$label, $href, $icon] = $item; $children = $item[3] ?? []; ?>
                        <?php if ($href === null): ?>
                            <span class="nav-link app-nav-link disabled" aria-disabled="true"
                                  title="<?= $e(Lang::get('nav.coming_soon')) ?>">
                                <span class="app-nav-icon"><i class="bi <?= $e($icon) ?>" aria-hidden="true"></i></span>
                                <span class="app-nav-label"><?= $e($label) ?></span>
                            </span>
                        <?php elseif ($children === []): ?>
                            <a class="nav-link app-nav-link<?= $i === $activeIndex ? ' active' : '' ?>"
                               href="<?= $e(Url::to($href)) ?>"<?= $i === $activeIndex ? ' aria-current="page"' : '' ?>>
                                <span class="app-nav-icon"><i class="bi <?= $e($icon) ?>" aria-hidden="true"></i></span>
                                <span class="app-nav-label"><?= $e($label) ?></span>
                            </a>
                        <?php else: ?>
                            <?php $open = $i === $activeIndex; ?>
                            <div class="app-nav-item">
                                <a class="nav-link app-nav-link<?= $open ? ' active' : '' ?>"
                                   href="<?= $e(Url::to($href)) ?>"<?= $open ? ' aria-current="page"' : '' ?>>
                                    <span class="app-nav-icon"><i class="bi <?= $e($icon) ?>" aria-hidden="true"></i></span>
                                    <span class="app-nav-label"><?= $e($label) ?></span>
                                </a>
                                <button type="button" class="app-nav-caret" data-bs-toggle="collapse"
                                        data-bs-target="#app-subnav-<?= $i ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>"
                                        aria-controls="app-subnav-<?= $i ?>"
                                        aria-label="<?= $e(Lang::get('nav.toggle_submenu')) ?>">
                                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                </button>
                                <div class="collapse<?= $open ? ' show' : '' ?>" id="app-subnav-<?= $i ?>">
                                    <nav class="app-nav-sub">
                                        <?php foreach ($children as $child): ?>
                                            <?php [$subLabel, $subHref] = $child; $subStatus = $child[2] ?? null; ?>
                                            <a class="app-nav-sub-link<?= $subActive($subHref) ? ' active' : '' ?>"
                                               href="<?= $e(Url::to($subHref)) ?>"<?= $subActive($subHref) ? ' aria-current="page"' : '' ?>>
                                                <?php if ($subStatus !== null): ?>
                                                    <span class="app-nav-sub-dot st-<?= $e($subStatus) ?>" aria-hidden="true"></span>
                                                <?php endif; ?>
                                                <span class="app-nav-sub-label"><?= $e($subLabel) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            </div>
        </aside>
    <?php endif; ?>

    <main class="app-main">
        <div class="<?= $hasSidebar ? 'container-fluid px-3 px-lg-4' : 'container' ?> py-4">
            <?= $content ?>
        </div>
    </main>
</div>

<?php if ($hasBottomNav): ?>
    <nav class="app-bottom-nav" aria-label="<?= $e(Lang::get('nav.menu')) ?>">
        <?php foreach ($bottomNav as $bi => [$bHref, $bLabel, $bIcon]): ?>
            <?php
            // Insert the raised create FAB in the middle of the admin bar.
            if ($showFab && $bi === 2): ?>
                <div class="app-fab-slot">
                    <button type="button" class="app-fab" data-bs-toggle="offcanvas"
                            data-bs-target="#appCreateSheet" aria-controls="appCreateSheet"
                            aria-label="<?= $e(Lang::get('nav.quick_create')) ?>">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                    <span class="app-fab-label"><?= $e(Lang::get('nav.new')) ?></span>
                </div>
            <?php endif; ?>
            <a class="app-bottom-link<?= $bi === $bottomActive ? ' active' : '' ?>"
               href="<?= $e(Url::to($bHref)) ?>"<?= $bi === $bottomActive ? ' aria-current="page"' : '' ?>>
                <i class="bi <?= $e($bIcon) ?>" aria-hidden="true"></i>
                <span class="app-bottom-label"><?= $e($bLabel) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($showFab): ?>
        <div class="offcanvas offcanvas-bottom app-create-sheet" tabindex="-1" id="appCreateSheet"
             aria-labelledby="appCreateSheetTitle">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h6 mb-0" id="appCreateSheetTitle"><?= $e(Lang::get('nav.quick_create')) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                        aria-label="<?= $e(Lang::get('nav.close_menu')) ?>"></button>
            </div>
            <div class="offcanvas-body">
                <div class="app-create-grid">
                    <?php foreach ($fabActions as [$aHref, $aLabel, $aIcon]): ?>
                        <a class="app-create-action" href="<?= $e(Url::to($aHref)) ?>">
                            <span class="app-create-ic"><i class="bi <?= $e($aIcon) ?>" aria-hidden="true"></i></span>
                            <span class="app-create-txt"><?= $e($aLabel) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script src="<?= $e($base) ?>/assets/vendor/jquery.min.js"></script>
<script src="<?= $e($base) ?>/assets/vendor/bootstrap.bundle.min.js"></script>
<script src="<?= $e($base) ?>/assets/js/app.js?v=<?= (int) @filemtime(dirname(__DIR__) . '/public/assets/js/app.js') ?>"></script>
</body>
</html>
