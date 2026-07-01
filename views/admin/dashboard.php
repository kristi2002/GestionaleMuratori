<?php
use App\Support\Url;
use App\Support\View;
$e = static fn (?string $v): string => View::e($v);
?>
<h1 class="h4 mb-3">Pannello Amministratore</h1>
<p class="text-muted">Benvenuto, <?= $e($user['name'] ?? '') ?>. Da qui gestirai clienti, progetti, magazzino e report.</p>

<div class="row g-3 mt-1">
    <?php
    $cards = [
        ['Clienti', 'Anagrafica clienti e referenti.', '/admin/clients', null],
        ['Progetti', 'Cantieri e commesse.', '/admin/projects', null],
        ['Magazzino', 'Articoli, carichi e registro movimenti.', '/admin/warehouse', null],
        ['Interventi', 'Pianificazione e assegnazione operai.', '/admin/interventions', null],
        ['Report', 'Esportazione PDF ed Excel per progetto.', '/admin/projects', null],
    ];
    foreach ($cards as [$titleCard, $descCard, $href, $phase]):
    ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-1"><?= $e($titleCard) ?></h2>
                    <p class="small text-muted mb-2"><?= $e($descCard) ?></p>
                    <?php if ($href !== null): ?>
                        <a class="btn btn-sm btn-success" href="<?= $e(Url::to($href)) ?>">Apri</a>
                    <?php else: ?>
                        <span class="badge text-bg-secondary"><?= $e($phase) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
