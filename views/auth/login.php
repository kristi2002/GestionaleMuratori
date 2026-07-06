<?php
use App\Support\View;
use App\Support\Url;
$e = static fn (?string $v): string => View::e($v);
?>
<div class="row justify-content-center">
    <div class="col-12 col-sm-9 col-md-6 col-lg-5">
        <div class="card shadow-sm mt-4">
            <div class="card-body p-4">
                <h1 class="h4 mb-1 d-flex align-items-center gap-2">
                    <span class="app-brand-chip">GM</span> Gestionale Muratori
                </h1>
                <p class="text-muted small mb-4">Accedi per continuare</p>

                <div id="login-error" class="alert alert-danger d-none" role="alert"></div>

                <form id="login-form" action="<?= $e(Url::to('/login')) ?>" method="post" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100" id="login-submit">Accedi</button>
                </form>
            </div>
        </div>

        <div class="card mt-3 border-0 bg-transparent">
            <div class="card-body p-2 small text-muted">
                <strong>Credenziali demo</strong> (password: <code>password</code>)<br>
                admin@gestionale.local · worker1@gestionale.local · client1@gestionale.local
            </div>
        </div>
    </div>
</div>
