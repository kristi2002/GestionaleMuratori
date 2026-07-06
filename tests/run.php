<?php
/**
 * Test runner — full suite against a dedicated test database.
 *
 *   php tests/run.php
 *
 * Never touches the development database: everything runs on
 * GM_TEST_DB_* (defaults: 127.0.0.1:3307, root/test — the throwaway
 * MySQL 8 container from tests/start-test-db.ps1) with database
 * "gestionale_muratori_test" and a scratch uploads directory.
 *
 * Flow: recreate DB -> migrate -> seed -> in-process unit/service tests
 * -> boot `php -S` -> HTTP end-to-end simulation -> summary.
 */
declare(strict_types=1);

$ROOT = dirname(__DIR__);
require __DIR__ . '/lib.php';

// --- Test environment (real env vars win over .env — see Support\Env) --------
$TEST_DB = [
    'host' => getenv('GM_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('GM_TEST_DB_PORT') ?: '3307',
    'user' => getenv('GM_TEST_DB_USER') ?: 'root',
    'pass' => getenv('GM_TEST_DB_PASS') !== false ? getenv('GM_TEST_DB_PASS') : 'test',
    'name' => 'gestionale_muratori_test',
];
$UPLOADS = $ROOT . '/tests/.uploads';
$PORT    = (int) (getenv('GM_TEST_HTTP_PORT') ?: 8099);

putenv('DB_HOST=' . $TEST_DB['host']);
putenv('DB_PORT=' . $TEST_DB['port']);
putenv('DB_USER=' . $TEST_DB['user']);
putenv('DB_PASS=' . $TEST_DB['pass']);
putenv('DB_NAME=' . $TEST_DB['name']);
putenv('UPLOADS_PATH=' . $UPLOADS);
putenv('APP_DEBUG=false');
putenv('APP_URL=http://127.0.0.1:' . $PORT);
putenv('SESSION_SECURE=false');
// Keep the Giornale dei Lavori weather auto-fill offline during tests (no Open-Meteo call).
putenv('WEATHER_ENABLED=false');

require $ROOT . '/src/bootstrap.php';

use App\Support\Database;

// --- Helpers ------------------------------------------------------------------
function runChild(string $script, string $root): array
{
    $php  = PHP_BINARY;
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([$php, $script], $desc, $pipes, $root);
    if (!is_resource($proc)) {
        return [1, 'proc_open failed'];
    }
    $out  = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    return [$code, $out];
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

// --- 0. Sanity: test DB reachable ----------------------------------------------
echo "Test database: {$TEST_DB['user']}@{$TEST_DB['host']}:{$TEST_DB['port']}/{$TEST_DB['name']}\n";
try {
    $server = Database::serverConnection(require $ROOT . '/config/config.php');
} catch (\Throwable $e) {
    fwrite(STDERR, "\nERRORE: database di test non raggiungibile.\n{$e->getMessage()}\n");
    fwrite(STDERR, "Avvia il container di test:  powershell tests/start-test-db.ps1\n");
    exit(2);
}

// --- 1. Fresh schema + seed ----------------------------------------------------
echo "[setup] recreate database…\n";
$server->exec("DROP DATABASE IF EXISTS `{$TEST_DB['name']}`");

[$code, $out] = runChild($ROOT . '/database/migrate.php', $ROOT);
if ($code !== 0) {
    fwrite(STDERR, "MIGRATE FAILED:\n$out\n");
    exit(2);
}
echo "[setup] migrations applied\n";

[$code, $out] = runChild($ROOT . '/database/seed.php', $ROOT);
if ($code !== 0) {
    fwrite(STDERR, "SEED FAILED:\n$out\n");
    exit(2);
}
echo "[setup] seed loaded\n";

rrmdir($UPLOADS);
mkdir($UPLOADS, 0775, true);

// --- 2. In-process unit + service tests ----------------------------------------
$pdo = Database::pdo();
foreach (glob(__DIR__ . '/cases/0*.php') ?: [] as $case) {
    require $case;
}

// --- 3. HTTP end-to-end simulation ---------------------------------------------
echo "\n[e2e] starting dev server on port {$PORT}…\n";
$serverProc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $PORT, '-t', 'public', 'public/index.php'],
    [1 => ['file', $ROOT . '/tests/.server.log', 'w'], 2 => ['file', $ROOT . '/tests/.server.log', 'a']],
    $unusedPipes,
    $ROOT
);
if (!is_resource($serverProc)) {
    fwrite(STDERR, "Cannot start test HTTP server\n");
    exit(2);
}

$baseUrl = 'http://127.0.0.1:' . $PORT;
$ready   = false;
for ($i = 0; $i < 50; $i++) {
    usleep(100_000);
    $probe = @file_get_contents($baseUrl . '/health');
    if ($probe !== false && str_contains($probe, '"ok":true')) {
        $ready = true;
        break;
    }
}

if (!$ready) {
    fwrite(STDERR, "Test HTTP server did not become ready — log:\n" . (string) @file_get_contents($ROOT . '/tests/.server.log') . "\n");
    proc_terminate($serverProc);
    exit(2);
}
echo "[e2e] server ready\n";

try {
    foreach (glob(__DIR__ . '/cases/1*.php') ?: [] as $case) {
        require $case;
    }
} finally {
    proc_terminate($serverProc);
    proc_close($serverProc);
}

// --- 4. Summary -----------------------------------------------------------------
exit(T::summary());
