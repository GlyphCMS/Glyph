<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\services\system\SystemBackupManager;

if (!class_exists('ZipArchive')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-system-backup-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    foreach (['content', 'data', 'uploads', 'themes', 'plugins', 'config', 'storage/backups'] as $dir) {
        $filesystem->ensureDirectoryExists($root . '/' . $dir);
    }

    file_put_contents($root . '/content/example.txt', 'content');
    file_put_contents($root . '/config/example.php', '<?php return [];');

    $manager = new SystemBackupManager(
        filesystem: $filesystem,
        rootPath: $root,
        backupPath: $root . '/storage/backups',
        paths: [
            'content' => $root . '/content',
            'data' => $root . '/data',
            'uploads' => $root . '/uploads',
            'themes' => $root . '/themes',
            'plugins' => $root . '/plugins',
            'config' => $root . '/config',
        ],
    );

    $archivePath = $manager->createBackupArchive();

    if (!is_file($archivePath)) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($archivePath) !== true) {
        return false;
    }

    $foundContent = $zip->locateName('content/example.txt') !== false;
    $foundManifest = $zip->locateName('backup-manifest.json') !== false;
    $zip->close();

    return $foundContent && $foundManifest;
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
