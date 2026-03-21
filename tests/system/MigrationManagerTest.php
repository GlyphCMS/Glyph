<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\system\MigrationManager;
use Glyph\services\system\VersionStateManager;

$root = sys_get_temp_dir() . '/glyph-migrations-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $paths = [
        'data_cache' => $root . '/data/cache',
        'data_indexes' => $root . '/data/indexes',
        'data_media' => $root . '/data/media',
        'data_redirects' => $root . '/data/redirects',
        'data_sessions' => $root . '/data/sessions',
        'data_system' => $root . '/data/system',
        'data_users' => $root . '/data/users',
        'storage' => $root . '/storage',
        'storage_logs' => $root . '/storage/logs',
    ];

    foreach ([$root, $root . '/data', $root . '/storage'] as $dir) {
        $filesystem->ensureDirectoryExists($dir);
    }

    $manager = new MigrationManager(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        versionStateManager: new VersionStateManager(new PhpConfigWriter($filesystem), $paths['data_system'] . '/version.php'),
        appConfig: ['version' => '0.1.0'],
        versioningConfig: ['schema_version' => '1.0.0', 'auto_run_migrations' => true],
        paths: $paths,
    );

    $result = $manager->runPending();

    if ($result->wasAlreadyCurrent) {
        return false;
    }

    if (count($result->appliedMigrationIds) !== 2) {
        return false;
    }

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            return false;
        }
    }

    $stateFile = $paths['data_system'] . '/version.php';
    if (!is_file($stateFile)) {
        return false;
    }

    $state = require $stateFile;

    if (($state['app_version'] ?? '') !== '0.1.0') {
        return false;
    }

    if (($state['schema_version'] ?? '') !== '1.0.0') {
        return false;
    }

    if (count($state['applied_migrations'] ?? []) !== 2) {
        return false;
    }

    $secondRun = $manager->runPending();
    if (!$secondRun->wasAlreadyCurrent) {
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
