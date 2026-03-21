<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\system\VersionState;
use Glyph\services\system\VersionStateManager;

$root = sys_get_temp_dir() . '/glyph-version-state-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $manager = new VersionStateManager(
        configWriter: new PhpConfigWriter($filesystem),
        statePath: $root . '/version.php',
    );

    $default = $manager->load('0.1.0', '1.0.0');
    if ($default->appVersion !== '0.1.0' || $default->schemaVersion !== '1.0.0') {
        return false;
    }

    $manager->save(new VersionState(
        appVersion: '0.2.0',
        schemaVersion: '1.1.0',
        lastMigratedAt: '2026-03-08T00:00:00+00:00',
        appliedMigrations: ['m1', 'm2'],
    ));

    $loaded = $manager->load('0.1.0', '1.0.0');

    if ($loaded->appVersion !== '0.2.0' || $loaded->schemaVersion !== '1.1.0') {
        return false;
    }

    if ($loaded->appliedMigrations !== ['m1', 'm2']) {
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
