<?php
use App\Support\Lang;
use App\Support\Shortcuts;
use App\Support\Url;
use App\Support\View;

/** @var array|null $user */
/** @var array<string,string> $shortcuts  effective action => key */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$isAdmin = ($user['role'] ?? '') === 'admin';
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
                <span class="small text-muted fw-normal"><?= $e($t($isAdmin ? 'shortcuts.edit_hint' : 'shortcuts.nav_hint')) ?></span>
            </div>
            <div class="card-body">
                <?php if (!$isAdmin): ?>
                    <p class="text-muted small mb-3"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= $e($t('shortcuts.admin_only_note')) ?></p>
                    <ul class="list-unstyled app-kbd-list mb-0">
                        <?php foreach (Shortcuts::NAV as $action => [$defKey, $href, $labelKey]): ?>
                            <li class="d-flex align-items-center justify-content-between">
                                <span><?= $e($t($labelKey)) ?></span>
                                <span class="app-kbd-combo">
                                    <kbd class="app-kbd">G</kbd>
                                    <span class="app-kbd-then"><?= $e($t('shortcuts.then')) ?></span>
                                    <kbd class="app-kbd"><?= $e(strtoupper($shortcuts[$action] ?? $defKey)) ?></kbd>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <form class="js-shortcuts-form" data-url="<?= $e(Url::to('/shortcuts')) ?>" data-saved="<?= $e($t('shortcuts.saved')) ?>">
                        <ul class="list-unstyled app-kbd-list mb-3">
                            <?php foreach (Shortcuts::NAV as $action => [$defKey, $href, $labelKey]): ?>
                                <li class="d-flex align-items-center justify-content-between">
                                    <span><?= $e($t($labelKey)) ?></span>
                                    <span class="app-kbd-combo">
                                        <kbd class="app-kbd">G</kbd>
                                        <span class="app-kbd-then"><?= $e($t('shortcuts.then')) ?></span>
                                        <input type="text" class="app-kbd-input js-shortcut-key"
                                               name="shortcuts[<?= $e($action) ?>]"
                                               value="<?= $e(strtoupper($shortcuts[$action] ?? $defKey)) ?>"
                                               data-default="<?= $e(strtoupper($defKey)) ?>"
                                               maxlength="1" autocomplete="off" spellcheck="false"
                                               aria-label="<?= $e($t($labelKey)) ?>">
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check-lg" aria-hidden="true"></i> <?= $e($t('common.save')) ?>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-shortcuts-reset">
                                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?= $e($t('shortcuts.reset')) ?>
                            </button>
                            <span class="small ms-1 js-shortcuts-msg" role="status"></span>
                        </div>
                    </form>
                <?php endif; ?>
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
