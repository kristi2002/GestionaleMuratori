<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $invoice */
/** @var array<int,string> $errors */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.invoices.xml_title')) ?></h1>
        <p class="text-muted mb-0"><?= $e((string) $invoice['number']) ?> — <?= $e((string) $invoice['client_name']) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/invoices/' . $invoice['id'] . '/edit'], null) ?>
</div>

<div class="card">
    <div class="card-body">
        <div class="alert alert-warning" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i><?= $e($t('admin.invoices.xml_not_ready')) ?>
        </div>
        <ul class="mb-3">
            <?php foreach ($errors as $err): ?>
                <li><?= $e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/company')) ?>"><?= $e($t('admin.company.title')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices/' . $invoice['id'] . '/edit')) ?>"><?= $e($t('admin.invoices.edit')) ?></a>
        </div>
    </div>
</div>
