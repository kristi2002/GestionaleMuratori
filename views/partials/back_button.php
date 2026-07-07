<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/**
 * Back button: render with View::render('partials/back_button', ['href' => ...], null).
 * @var string|null $href  Destination path (defaults to the admin dashboard).
 * @var string|null $label Button text (defaults to common.back).
 */
$e = static fn (?string $v): string => View::e($v);
?>
<a class="btn btn-outline-success" href="<?= $e(Url::to($href ?? '/admin')) ?>">
    <i class="bi bi-arrow-left" aria-hidden="true"></i> <?= $e($label ?? Lang::get('common.back')) ?>
</a>
