<?php
use App\Support\View;

/**
 * Page header: large title + muted subtitle on the left, optional action
 * buttons on the right (matches the "muratori design" mockups). Render with
 *   View::render('partials/page_head', [
 *       'title'    => $t('admin.clients.title'),
 *       'subtitle' => $t('admin.clients.subtitle'),   // optional
 *       'actions'  => $actionsHtml,                    // optional pre-rendered HTML
 *   ], null)
 * @var string      $title
 * @var string|null $subtitle
 * @var string|null $actions  Pre-rendered, already-escaped HTML for the action area.
 */
$e = static fn (?string $v): string => View::e($v);
$subtitle = $subtitle ?? null;
$actions  = $actions ?? null;
?>
<div class="app-page-head">
    <div class="app-page-head-main">
        <h1 class="app-page-title"><?= $e($title) ?></h1>
        <?php if ($subtitle !== null && $subtitle !== ''): ?>
            <p class="app-page-sub"><?= $e($subtitle) ?></p>
        <?php endif; ?>
    </div>
    <?php if ($actions !== null && $actions !== ''): ?>
        <div class="app-page-actions"><?= $actions ?></div>
    <?php endif; ?>
</div>
