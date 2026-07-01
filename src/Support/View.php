<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Minimal PHP template renderer with a single optional layout.
 * Shared data (base path, current user) is merged into every render.
 */
final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $data    = array_merge(self::$shared, $data);
        $content = self::renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }
        return self::renderFile($layout, array_merge($data, ['content' => $content]));
    }

    private static function renderFile(string $template, array $data): string
    {
        $file = dirname(__DIR__, 2) . '/views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View non trovata: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    /** Convenience escaper for use inside templates. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
