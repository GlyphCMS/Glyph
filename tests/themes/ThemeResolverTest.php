<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\services\themes\ThemeResolver;

$root = sys_get_temp_dir() . '/glyph-theme-resolver-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/default/assets');
    $filesystem->ensureDirectoryExists($root . '/midnight');
    file_put_contents($root . '/default/theme.json', json_encode([
        'name' => 'Default Theme',
        'version' => '1.2.3',
        'author' => 'Glyph',
        'description' => 'Default theme description.',
        'screenshot' => 'assets/screenshot.svg',
    ], JSON_PRETTY_PRINT));
    file_put_contents($root . '/default/assets/screenshot.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    file_put_contents($root . '/default/assets/manifest.json', json_encode(['preview_image' => 'screenshot.svg'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/midnight/theme.json', json_encode(['name' => 'Midnight'], JSON_PRETTY_PRINT));

    $resolver = new ThemeResolver($filesystem, $root, 'default');
    $theme = $resolver->resolve('default');

    if ($theme->directoryName !== 'default') {
        return false;
    }

    if ($theme->name !== 'Default Theme') {
        return false;
    }

    if ($theme->version !== '1.2.3') {
        return false;
    }

    if ($theme->screenshotUrl === null || !str_contains($theme->screenshotUrl, '/themes/default/assets/screenshot.svg')) {
        return false;
    }

    if (($theme->assets['preview_image'] ?? '') !== 'screenshot.svg') {
        return false;
    }

    $themes = $resolver->listThemes();

    if (count($themes) !== 2) {
        return false;
    }

    $fallback = $resolver->resolve('bad/../theme');

    if ($fallback->directoryName !== 'default') {
        return false;
    }

    return true;
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($root);
    }
}
