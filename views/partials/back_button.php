<?php
/**
 * Back button — intentionally renders nothing.
 *
 * The persistent sidebar (and breadcrumbs on detail pages) provide navigation,
 * so the per-page "Indietro" buttons were removed. Kept as a no-op partial so
 * the existing View::render('partials/back_button', …) calls across the views
 * remain valid; those call sites can be cleaned up over time.
 */
return;
