<?php
/**
 * Generate the VAPID key pair used by Web Push (App\Support\WebPush).
 *
 *   php scripts/vapid-keygen.php            # create config/vapid_private.pem if absent
 *   php scripts/vapid-keygen.php --force    # overwrite an existing key (invalidates subscriptions)
 *
 * Writes the P-256 private key to config/vapid_private.pem (git-ignored) and prints
 * the public application-server key. After running, set VAPID_SUBJECT in .env to a
 * mailto: or https: contact address, e.g.  VAPID_SUBJECT=mailto:admin@example.com
 *
 * openssl only — no ext-gmp, no Composer dependency.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Config;
use App\Support\WebPush;

$path  = (string) Config::get('push.private_key_path', dirname(__DIR__) . '/config/vapid_private.pem');
$force = in_array('--force', array_slice($argv, 1), true);

if (is_file($path) && !$force) {
    fwrite(STDERR, "Un file chiave esiste già: {$path}\n");
    fwrite(STDERR, "Usa --force per sovrascriverlo (invaliderà tutte le iscrizioni push esistenti).\n");
    // Still print the existing public key for convenience.
    try {
        echo "Chiave pubblica (VAPID) attuale:\n  " . WebPush::publicKey() . "\n";
    } catch (\Throwable $e) {
        // ignore
    }
    exit(1);
}

// Both key generation and export read openssl.cnf; a broken OPENSSL_CONF (common on
// Windows/XAMPP) makes them fail. Runtime signing is unaffected — this is generation
// only — so try the ambient config first, then fall back to well-known locations,
// and reuse whichever works for the export too.
$ecArgs  = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
$configs = array_merge([null], array_filter([
    'C:/xampp/apache/conf/openssl.cnf',
    'C:/xampp/php/extras/openssl/openssl.cnf',
    '/etc/ssl/openssl.cnf',
    '/usr/lib/ssl/openssl.cnf',
], 'is_file'));

$pem = '';
$ok  = false;
foreach ($configs as $cnf) {
    $opts = $cnf === null ? $ecArgs : $ecArgs + ['config' => $cnf];
    $res  = @openssl_pkey_new($opts);
    if ($res !== false && @openssl_pkey_export($res, $pem, null, $cnf === null ? null : ['config' => $cnf])) {
        $ok = true;
        break;
    }
}
if (!$ok) {
    fwrite(STDERR, "ERRORE: impossibile generare/esportare la chiave EC (openssl).\n");
    fwrite(STDERR, "Verifica la variabile OPENSSL_CONF o installa un openssl.cnf valido.\n");
    while ($e = openssl_error_string()) {
        fwrite(STDERR, "  openssl: {$e}\n");
    }
    exit(1);
}

if (@file_put_contents($path, $pem) === false) {
    fwrite(STDERR, "ERRORE: impossibile scrivere {$path}\n");
    exit(1);
}
@chmod($path, 0600);

echo "Chiave privata VAPID scritta in:\n  {$path}\n\n";
echo "Chiave pubblica (application server key):\n  " . WebPush::publicKeyFromPem($pem) . "\n\n";
echo "Ora imposta in .env:\n  VAPID_SUBJECT=mailto:tuo-indirizzo@example.com\n";
echo "(opzionale) PUSH_ENABLED=true  — attivo per default quando la chiave e il subject sono presenti.\n";

exit(0);
