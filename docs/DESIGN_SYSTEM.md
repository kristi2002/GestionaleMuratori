# Design System — "muratori design" refresh

The reference mockups live in `muratori design/*.png`. The palette is **dark navy +
orange**, dark shell is the default. All components below live in
`public/assets/css/app.css` (section headers "MOCKUP COMPONENT KIT" and the earlier
`.app-hero` / `.gm-kpi` blocks) and flip automatically between light/dark via the
`--surface-*` / `--ink-*` tokens. **Reuse these classes — do not invent new colors or
hardcode hex in views.**

## Hard rules when editing views
- All user-facing text through `Lang::get($key)` / `Lang::label($group,$val)` — never
  hardcode Italian. Add new keys to `lang/it.php` if needed.
- Escape every dynamic value with `View::e(...)`.
- **Do not rename or drop any controller-provided `$variable`** or change a partial's
  call signature — views are presentation only; the data contract must stay identical.
- Buttons: primary action = `btn btn-success` (aliased to orange). Secondary =
  `btn btn-outline-secondary`. Real "confirm/complete" green = `btn`+inline style is
  avoided; use `.app-status-success` semantics, not raw green, unless the mockup shows a
  distinct green button (e.g. "Registra Pagamento").
- Run `C:/xampp/php/php.exe -l <file>` after editing; keep files < 500 lines.

## Page skeleton (every list/detail page)
```php
<?= View::render('partials/page_head', [
    'title'    => $t('admin.x.title'),
    'subtitle' => $t('admin.x.subtitle'),
    'actions'  => '<a class="btn btn-success" href="'.$e(Url::to('/admin/x/create')).'">'
                . '<i class="bi bi-plus-lg"></i> '.$e($t('admin.x.new')).'</a>',
], null) ?>
```
Then (in order, as the mockup shows): KPI row → pill filters → main content
(± right rail).

## Components (class → use)
- **KPI row**: `.row.g-3` of `.col`; each `<div class="card gm-kpi h-100">` with
  `.gm-kpi-ic` (bi icon), `.gm-kpi-val` (number), `.gm-kpi-lab` (label), optional
  `.gm-kpi-sub`. Accent variants: `.ok .warn .is-info .is-purple .is-danger .alert`.
  `.is-primary` = orange ring (the "selected" KPI). Trend: `.gm-kpi-trend.up|.down`
  with a `bi-arrow-up/down`. Solid colored tiles: `.gm-kpi-solid.is-success|is-warn|
  is-danger|is-info|is-purple` (Andamento Economico).
- **Pill filters**: `View::render('partials/filter_pills', ['pills'=>[
  ['label'=>..,'href'=>..,'active'=>bool,'count'=>?,'dot'=>'success']]], null)`.
  Use in place of a `<select>` when the mockup shows tabs.
- **Hero banner** (dashboard only): `.app-hero` (see `views/admin/dashboard.php`).
- **Record cards grid**: `.app-record-card` (orange top accent on hover). Add a
  media header with `.app-card-media` + `.app-card-media-glyph` (bi icon) and place a
  status badge in it. Progress: `.app-meter` > `.app-meter-track` > `.app-meter-fill`
  (+`.is-success/.is-danger`) and `.app-meter-val`. Footer avatar stack: `.app-avatars`
  > `.app-avatar` (initials or img); overflow `.app-avatar.is-more` ("+3").
- **Two-column main + right rail**: wrap in `.app-cols` (grid: 1fr + 340px, stacks
  < lg). Rail = `.app-rail` > `.app-rail-card` (`.app-rail-title` heading). Detail rows:
  `<dl class="app-dl"><div class="app-dl-row"><dt>..</dt><dd>..</dd></div></dl>`.
  Empty rail state: `.app-rail-empty`.
- **Tables**: `table table-hover align-middle`; first column often avatar+name; status
  via `partials/status_badge`; severity rows `tr.sev-bad` / `tr.sev-warn`.
- **Stepper** (S.A.L.): `.app-stepper` > `.app-step(.done|.current)` > `.app-step-dot`
  + `.app-step-label`.
- **Alert banners**: `.app-banner.is-warn` (filled orange) / `.is-danger` / `.is-soft`;
  glowing hero alert `.app-banner-glow`.
- **Star rating**: `.app-stars` with `bi-star-fill` / `bi-star`.
- **Section heading** (no card): `.app-section-title`.
- **Charts**: reuse `partials/chart_line`, `chart_donut`, `chart_hbars`, `chart_vbars`
  (see existing usage in statistics/financials views).

## Existing reference implementations
- `views/admin/dashboard.php` — hero + KPI row + spark trends + alert tables.
- `views/admin/projects/index.php` — page_head + KPI + pills + record-card grid (the
  canonical list-page example after this refresh).
