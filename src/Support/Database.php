<?php
declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection factory. Single shared connection per request via pdo().
 * No ORM — callers use prepared statements directly.
 */
final class Database
{
    private static ?PDO $instance = null;

    /** Shared application connection (selects the configured database). */
    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::connect(self::config());
        }
        return self::$instance;
    }

    /** Build a fresh connection to the configured database. */
    public static function connect(array $config): PDO
    {
        $db  = $config['db'];
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['port'],
            $db['name']
        );
        return self::makePdo($dsn, $db);
    }

    /** Connection to the MySQL server WITHOUT selecting a database (for CREATE DATABASE). */
    public static function serverConnection(array $config): PDO
    {
        $db  = $config['db'];
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $db['host'], $db['port']);
        return self::makePdo($dsn, $db);
    }

    private static function makePdo(string $dsn, array $db): PDO
    {
        try {
            return new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private static function config(): array
    {
        return require dirname(__DIR__, 2) . '/config/config.php';
    }
}
