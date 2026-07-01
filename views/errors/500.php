<?php
use App\Support\Url;
use App\Support\View;
$e = static fn (?string $v): string => View::e($v);
?>
<div class="text-center py-5">
    <h1 class="display-6 text-danger">500</h1>
    <p class="lead">Si è verificato un errore imprevisto.</p>
    <p class="text-muted">Riprova tra qualche istante. Se il problema persiste, contatta l'amministratore.</p>
    <a class="btn btn-success" href="<?= $e(Url::to('/')) ?>">Torna alla home</a>
</div>
