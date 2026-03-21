<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\services\plugins\PluginResolver;

$root = sys_get_temp_dir() . '/glyph-plugin-resolver-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/hello');
    file_put_contents($root . '/hello/plugin.json', json_encode([
        'name' => 'Hello',
        'version' => '1.0.0',
        'author' => 'Glyph',
        'description' => 'Demo plugin',
        'homepage' => 'https://example.com/plugin',
        'required_plugins' => ['core-tools'],
    ], JSON_PRETTY_PRINT));

    $resolver = new PluginResolver($filesystem, $root, ['enabled' => ['hello']]);
    $plugins = $resolver->listPlugins();

    if (count($plugins) !== 1) {
        return false;
    }

    if (!$plugins[0]->isEnabled) {
        return false;
    }

    if ($plugins[0]->name !== 'Hello') {
        return false;
    }

    if ($plugins[0]->homepageUrl !== 'https://example.com/plugin') {
        return false;
    }

    if ($plugins[0]->requiredPlugins !== ['core-tools']) {
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
