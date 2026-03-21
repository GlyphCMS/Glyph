<?php

declare(strict_types=1);

function glyphRequireFresh(string $filePath): void
{
    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($filePath, true);
    }

    require $filePath;
}

spl_autoload_register(static function (string $className): void {
    $prefix = 'Glyph\\';
    $baseDirectory = dirname(__DIR__) . '/src/';

    if (!str_starts_with($className, $prefix)) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass) . '.php';
    $filePath = $baseDirectory . $relativePath;

    if (is_file($filePath)) {
        glyphRequireFresh($filePath);
    }
});
