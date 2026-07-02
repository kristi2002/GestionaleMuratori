<?php
/**
 * Create (or update the password of) an admin user — for production setups
 * that skip the demo seed.
 *
 *   php scripts/create-admin.php "Full Name" admin@example.com 'password'
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\AuthController;
use App\Models\UserModel;

if ($argc < 4) {
    fwrite(STDERR, "Uso: php scripts/create-admin.php \"Nome\" email password\n");
    exit(1);
}

[, $name, $email, $password] = $argv;

if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "ERRORE: email non valida.\n");
    exit(1);
}
if (strlen($password) < AuthController::MIN_PASSWORD_LENGTH) {
    fwrite(STDERR, sprintf("ERRORE: la password deve avere almeno %d caratteri.\n", AuthController::MIN_PASSWORD_LENGTH));
    exit(1);
}

$model    = new UserModel();
$existing = $model->findByEmail($email);
$hash     = password_hash($password, PASSWORD_DEFAULT);

if ($existing !== null) {
    if ($existing['role'] !== 'admin') {
        fwrite(STDERR, "ERRORE: esiste già un utente non-admin con questa email.\n");
        exit(1);
    }
    $model->updatePassword((int) $existing['id'], $hash);
    echo "Password aggiornata per l'amministratore esistente: {$email}\n";
    exit(0);
}

$id = $model->create([
    'name'          => $name,
    'email'         => $email,
    'password_hash' => $hash,
    'role'          => 'admin',
    'client_id'     => null,
]);
echo "Amministratore creato (id {$id}): {$email}\n";
