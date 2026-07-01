<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Loads lang/it.php and resolves dot-notation keys.
 * All user-facing strings come from here (§2) — never hardcode Italian text.
 */
final class Lang
{
    /** @var array<string,mixed> */
    private static array $strings = [];

    public static function load(): void
    {
        if (self::$strings === []) {
            self::$strings = require dirname(__DIR__, 2) . '/lang/it.php';
        }
    }

    public static function get(string $key, ?string $fallback = null): string
    {
        self::load();
        $value = self::$strings;
        foreach (explode('.', $key) as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $fallback ?? $key;
            }
        }
        return is_string($value) ? $value : ($fallback ?? $key);
    }

    /** Translate a DB ENUM value, e.g. label('intervention_status', 'in_progress'). */
    public static function label(string $group, string $value): string
    {
        return self::get($group . '.' . $value, $value);
    }
}
