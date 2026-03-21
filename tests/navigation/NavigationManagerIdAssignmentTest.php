<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\navigation\NavigationManager;

$root = sys_get_temp_dir() . '/glyph-nav-id-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);
    $manager = new NavigationManager(new PhpConfigWriter($filesystem), $root, ['menus' => ['primary' => [], 'footer' => []]]);
    $manager->save([
        'primary' => [[
            'id' => '',
            'label' => 'Docs',
            'url' => '/docs',
            'target' => '_self',
            'parent_id' => '',
            'sort_order' => '0',
            'content_id' => '',
        ]],
        'footer' => [],
    ]);

    $loaded = require $root . '/navigation.php';
    $saved = $loaded['menus']['primary'][0]['id'] ?? '';

    return is_string($saved) && $saved !== '';
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
