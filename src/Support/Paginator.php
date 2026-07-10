<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Tiny pagination value object. Given a total row count and the requested page,
 * it computes the LIMIT/OFFSET a model needs and the metadata a view renders.
 * Page and per-page are clamped to sane bounds (no negative offsets, capped size).
 */
final class Paginator
{
    public readonly int $page;
    public readonly int $perPage;
    public readonly int $total;
    public readonly int $pages;
    public readonly int $offset;

    public function __construct(int $total, int $page, int $perPage = 25)
    {
        $this->total   = max(0, $total);
        $this->perPage = max(1, min(200, $perPage));
        $this->pages   = max(1, (int) ceil($this->total / $this->perPage));
        $this->page    = max(1, min($page, $this->pages));
        $this->offset  = ($this->page - 1) * $this->perPage;
    }

    /** Build from a request's ?page= parameter. */
    public static function fromRequest(Request $request, int $total, int $perPage = 25): self
    {
        return new self($total, (int) $request->input('page', 1), $perPage);
    }

    public function hasPages(): bool
    {
        return $this->pages > 1;
    }

    /** 1-based index of the first row on this page (0 when empty). */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset + 1;
    }

    /** 1-based index of the last row on this page. */
    public function to(): int
    {
        return min($this->offset + $this->perPage, $this->total);
    }
}
