<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $client null = create, row = edit */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $client !== null;
$pageTitle = $isEdit ? $t('admin.clients.edit') : $t('admin.clients.new');
$value     = static fn (string $key): string => (string) ($client[$key] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? (string) $client['name'] : $t('admin.clients.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/clients'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.clients.title'), '/admin/clients'],
    [$isEdit ? (string) $client['name'] : $t('admin.clients.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/clients')) ?>"
              data-redirect="<?= $e(Url::to('/admin/clients')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $client['id'] : '') ?>">

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.clients.name')) ?></label>
                <input type="text" class="form-control" name="name" value="<?= $e($value('name')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.clients.vat')) ?></label>
                <input type="text" class="form-control" name="vat_or_tax_id" value="<?= $e($value('vat_or_tax_id')) ?>">
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.clients.email')) ?></label>
                    <input type="email" class="form-control" name="email" value="<?= $e($value('email')) ?>">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.clients.phone')) ?></label>
                    <input type="text" class="form-control" name="phone" value="<?= $e($value('phone')) ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.clients.address')) ?></label>
                <input type="text" class="form-control" name="address" value="<?= $e($value('address')) ?>">
            </div>

            <!-- Dati fiscali per la fatturazione elettronica (FatturaPA / SdI). -->
            <fieldset class="border rounded p-3 mb-3">
                <legend class="float-none w-auto px-2 fs-6 text-muted mb-0"><?= $e($t('admin.clients.fiscal_section')) ?></legend>
                <div class="row">
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.kind')) ?></label>
                        <?php $kind = $value('client_kind') ?: 'business'; ?>
                        <select class="form-select" name="client_kind">
                            <?php foreach (['business', 'private', 'pa'] as $k): ?>
                                <option value="<?= $e($k) ?>" <?= $kind === $k ? 'selected' : '' ?>>
                                    <?= $e($t('admin.clients.kind_' . $k)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.partita_iva')) ?></label>
                        <input type="text" class="form-control" name="partita_iva" maxlength="11" value="<?= $e($value('partita_iva')) ?>">
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.codice_fiscale')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="codice_fiscale" maxlength="16" value="<?= $e($value('codice_fiscale')) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.codice_destinatario')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="codice_destinatario" maxlength="7" value="<?= $e($value('codice_destinatario')) ?>">
                        <div class="form-text"><?= $e($t('admin.clients.sdi_hint')) ?></div>
                    </div>
                    <div class="col-12 col-md-8 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.pec')) ?></label>
                        <input type="email" class="form-control" name="pec" value="<?= $e($value('pec')) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.cap')) ?></label>
                        <input type="text" class="form-control" name="cap" maxlength="10" value="<?= $e($value('cap')) ?>">
                    </div>
                    <div class="col-12 col-md-5 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.comune')) ?></label>
                        <input type="text" class="form-control" name="comune" maxlength="120" value="<?= $e($value('comune')) ?>">
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.provincia')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="provincia" maxlength="2" value="<?= $e($value('provincia')) ?>">
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.nazione')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="nazione" maxlength="2" value="<?= $e($value('nazione') ?: 'IT') ?>">
                    </div>
                </div>
            </fieldset>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.clients.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"><?= $e($value('notes')) ?></textarea>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/clients')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
