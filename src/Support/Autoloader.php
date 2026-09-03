<?php

declare(strict_types=1);

namespace VisualDesignerManager\Support;

final class Autoloader
{
    private function __construct()
    {
    }

    public static function register(string $prefix, string $baseDirectory): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDirectory = rtrim($baseDirectory, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($prefix, $baseDirectory): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            if ($relative === false || $relative === '') {
                return;
            }

            $file = $baseDirectory . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}
