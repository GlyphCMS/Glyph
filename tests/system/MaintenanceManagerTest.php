<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\system\MaintenanceManager;

$root = sys_get_temp_dir() . '/glyph-maintenance-manager-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $manager = new MaintenanceManager(new PhpConfigWriter($filesystem), $root);
    $settings = $manager->inputFromPost([
        'enabled' => '1',
        'message' => 'Down for maintenance',
    ]);

    $manager->save($settings);

    $saved = require $root . '/maintenance.php';

    if (($saved['enabled'] ?? false) !== true) {
        return false;
    }

    if (($saved['message'] ?? '') !== 'Down for maintenance') {
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
