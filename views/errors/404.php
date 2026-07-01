<?php
use App\Support\Url;
use App\Support\View;
$e = static fn (?string $v): string => View::e($v);
?>
<div class="text-center py-5">
    <h1 class="display-6">404</h1>
    <p class="lead">Pagina non trovata.</p>
    <a class="btn btn-success" href="<?= $e(Url::to('/')) ?>">Torna alla home</a>
</div>
