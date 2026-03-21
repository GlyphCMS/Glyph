<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\plugins\PluginSettingsStore;

$root = sys_get_temp_dir() . '/glyph-plugin-settings-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $store = new PluginSettingsStore(
        configWriter: new PhpConfigWriter($filesystem),
        settingsPath: $root . '/plugin-settings.php',
    );

    $store->saveSettings('hello_banner', ['message' => 'Hello']);

    $settings = $store->settingsFor('hello_banner');

    if (($settings['message'] ?? '') !== 'Hello') {
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
