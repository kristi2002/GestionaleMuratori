<?php
use App\Support\Lang;
use App\Support\View;

/** @var App\Support\Paginator $paginator */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Preserve the current query string (filters, search…) but drop the page param.
$basePath = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?');
$query    = $_GET;
unset($query['page']);
$href = static function (int $p) use ($basePath, $query): string {
    return $basePath . '?' . http_build_query(array_merge($query, ['page' => $p]));
};

// Compact window around the current page.
$win   = 2;
$start = max(1, $paginator->page - $win);
$end   = min($paginator->pages, $paginator->page + $win);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <span class="small text-muted">
        <?= $e(sprintf($t('common.pagination_summary'), $paginator->from(), $paginator->to(), $paginator->total)) ?>
    </span>
    <?php if ($paginator->hasPages()): ?>
        <nav aria-label="<?= $e($t('common.pagination')) ?>">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $paginator->page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($href($paginator->page - 1)) ?>" aria-label="<?= $e($t('common.prev')) ?>">&laquo;</a>
                </li>
                <?php if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $e($href(1)) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <li class="page-item <?= $p === $paginator->page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $e($href($p)) ?>"><?= $e((string) $p) ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($end < $paginator->pages): ?>
                    <?php if ($end < $paginator->pages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= $e($href($paginator->pages)) ?>"><?= $e((string) $paginator->pages) ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $paginator->page >= $paginator->pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $e($href($paginator->page + 1)) ?>" aria-label="<?= $e($t('common.next')) ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
