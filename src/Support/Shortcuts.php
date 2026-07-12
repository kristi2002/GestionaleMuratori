<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for the admin navigation keyboard shortcuts
 * ("G" then a key). Defaults live here; per-user overrides are stored as a small
 * JSON object {action: key} on users.shortcuts and merged on top. The JS handler
 * (public/assets/js/app.js) and the editor (views/shortcuts.php) both build off
 * the maps produced here, so the three never drift.
 */
final class Shortcuts
{
    /** action => [default key, href, label lang key]. Order = display order. */
    public const NAV = [
        'dashboard'     => ['d', '/admin',               'shortcuts.go_dashboard'],
        'statistics'    => ['t', '/admin/statistics',    'shortcuts.go_statistics'],
        'clients'       => ['c', '/admin/clients',       'shortcuts.go_clients'],
        'projects'      => ['p', '/admin/projects',      'shortcuts.go_projects'],
        'interventions' => ['i', '/admin/interventions', 'shortcuts.go_interventions'],
        'quotes'        => ['q', '/admin/quotes',        'shortcuts.go_quotes'],
        'invoices'      => ['f', '/admin/invoices',      'shortcuts.go_invoices'],
        'expenses'      => ['s', '/admin/expenses',      'shortcuts.go_expenses'],
        'warehouse'     => ['m', '/admin/warehouse',     'shortcuts.go_warehouse'],
        'attendance'    => ['b', '/admin/attendance',    'shortcuts.go_attendance'],
        'users'         => ['u', '/admin/users',         'shortcuts.go_users'],
        'exports'       => ['e', '/admin/exports',       'shortcuts.go_exports'],
    ];

    /** Keys that can't be reassigned (the "G" leader). */
    public const RESERVED = ['g'];

    /** @return array<string,string> action => default key */
    public static function defaults(): array
    {
        return array_map(static fn (array $v): string => $v[0], self::NAV);
    }

    /**
     * Effective action => key map (defaults with the user's valid overrides on top).
     *
     * @param string|null $json stored users.shortcuts JSON
     * @return array<string,string>
     */
    public static function effective(?string $json): array
    {
        return array_merge(self::defaults(), self::overrides($json));
    }

    /**
     * Effective key => href map, for the JS navigation handler.
     *
     * @return array<string,string>
     */
    public static function keyHrefMap(?string $json): array
    {
        $out = [];
        foreach (self::effective($json) as $action => $key) {
            $out[$key] = self::NAV[$action][1];
        }
        return $out;
    }

    /**
     * Decode stored overrides, keeping only known actions with a valid,
     * non-default, non-reserved single-letter key.
     *
     * @return array<string,string> action => key
     */
    public static function overrides(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $action => $key) {
            if (isset(self::NAV[$action]) && self::isValidKey((string) $key)) {
                $out[(string) $action] = strtolower((string) $key);
            }
        }
        return $out;
    }

    /**
     * Validate a raw {action: key} map submitted by the editor.
     *
     * @param array<mixed> $raw
     * @return array{0:array<string,string>,1:?string} [cleanOverrides, errorLangKey|null]
     */
    public static function validate(array $raw): array
    {
        $clean = [];
        foreach ($raw as $action => $key) {
            if (!isset(self::NAV[(string) $action])) {
                continue; // ignore unknown actions
            }
            $key = strtolower(trim((string) $key));
            if ($key === '') {
                continue; // empty = keep default
            }
            if (!self::isValidKey($key)) {
                return [[], 'shortcuts.err_invalid_key'];
            }
            $clean[(string) $action] = $key;
        }

        // The full effective set must have no two actions on the same key.
        $effective = array_merge(self::defaults(), $clean);
        if (count(array_unique($effective)) !== count($effective)) {
            return [[], 'shortcuts.err_duplicate'];
        }

        // Store only genuine overrides (differ from the default).
        $defaults = self::defaults();
        $overrides = [];
        foreach ($clean as $action => $key) {
            if ($defaults[$action] !== $key) {
                $overrides[$action] = $key;
            }
        }
        return [$overrides, null];
    }

    private static function isValidKey(string $key): bool
    {
        return strlen($key) === 1
            && ctype_alpha($key)
            && !in_array(strtolower($key), self::RESERVED, true);
    }
}
