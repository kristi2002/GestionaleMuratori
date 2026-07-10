<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/**
 * Clear-filters link: render inside a filter card with
 * View::render('partials/filter_clear', ['active' => bool, 'href' => '/admin/...'], null).
 * Renders nothing unless $active is true (i.e. at least one filter is applied).
 * @var bool $active
 * @var string $href  Base list URL to reset to (no query string).
 */
$e = static fn (?string $v): string => View::e($v);
if (empty($active)) {
    return;
}
?>
<div class="app-filter-clear mt-2">
    <a class="btn btn-sm btn-link text-decoration-none px-0" href="<?= $e(Url::to($href)) ?>">
        <i class="bi bi-x-circle" aria-hidden="true"></i> <?= $e(Lang::get('common.reset_filters')) ?>
    </a>
</div>
