<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\themes\ThemeAdminService;
use Glyph\services\themes\ThemeResolver;

$root = sys_get_temp_dir() . '/glyph-theme-admin-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/themes/default');
    $filesystem->ensureDirectoryExists($root . '/themes/midnight');
    $filesystem->ensureDirectoryExists($root . '/system');
    file_put_contents($root . '/themes/default/theme.json', json_encode(['name' => 'Default'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/themes/midnight/theme.json', json_encode(['name' => 'Midnight'], JSON_PRETTY_PRINT));

    $resolver = new ThemeResolver($filesystem, $root . '/themes', 'default');
    $service = new ThemeAdminService(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        themeResolver: $resolver,
        systemPath: $root . '/system',
        themesPath: $root . '/themes',
        siteConfig: ['site_name' => 'Glyph', 'tagline' => '', 'active_theme' => 'default', 'homepage_mode' => 'posts'],
        defaultTheme: 'default',
    );

    $service->activate('midnight');
    $siteConfig = require $root . '/system/site.php';

    if (($siteConfig['active_theme'] ?? '') !== 'midnight') {
        return false;
    }

    $serviceDelete = new ThemeAdminService(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        themeResolver: $resolver,
        systemPath: $root . '/system',
        themesPath: $root . '/themes',
        siteConfig: ['site_name' => 'Glyph', 'tagline' => '', 'active_theme' => 'default', 'homepage_mode' => 'posts'],
        defaultTheme: 'default',
    );

    $serviceDelete->delete('midnight');

    if (is_dir($root . '/themes/midnight')) {
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
