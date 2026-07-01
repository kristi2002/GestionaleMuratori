<?php
/**
 * Migration runner.
 *   php database/migrate.php
 *
 * Creates the database if missing, then applies any *.sql file in
 * database/migrations/ that has not been recorded in the `migrations` table.
 * Idempotent: already-applied files are skipped.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Database;

$config = require dirname(__DIR__) . '/config/config.php';

// 1. Ensure the database exists (connect to the server without selecting a DB).
$server = Database::serverConnection($config);
$server->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    str_replace('`', '', $config['db']['name'])
));
echo "Database pronto: {$config['db']['name']}\n";

// 2. Connect to the database and ensure the bookkeeping table exists.
$pdo = Database::connect($config);
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename VARCHAR(255) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_filename (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = $pdo->query('SELECT filename FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "  - salto $name (già applicata)\n";
        continue;
    }

    $sql = (string) file_get_contents($file);
    // Strip full-line SQL comments, then split on ";" followed by a newline.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = preg_split('/;\s*\n/', $sql) ?: [];

    try {
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            $pdo->exec($statement);
        }
        $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $stmt->execute([$name]);
        echo "  + applicata $name\n";
        $ran++;
    } catch (\Throwable $e) {
        fwrite(STDERR, "ERRORE in $name: {$e->getMessage()}\n");
        exit(1);
    }
}

echo $ran === 0 ? "Nessuna nuova migrazione.\n" : "Completato: $ran migrazione/i applicata/e.\n";
