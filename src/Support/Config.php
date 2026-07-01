<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Loads config/config.php and resolves dot-notation keys (mirrors Lang::get).
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$values === []) {
            self::$values = require dirname(__DIR__, 2) . '/config/config.php';
        }

        $value = self::$values;
        foreach (explode('.', $key) as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return $value;
    }
}
