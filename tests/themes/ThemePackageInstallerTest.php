<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;
use Glyph\adapters\filesystem\UploadedZipInspector;
use Glyph\services\themes\ThemePackageInstaller;

if (!class_exists('ZipArchive')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-theme-package-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/themes');
    $filesystem->ensureDirectoryExists($root . '/package/default-theme');
    file_put_contents($root . '/package/default-theme/theme.json', json_encode(['name' => 'Package Theme'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/package/default-theme/readme.txt', 'ok');

    $zipPath = $root . '/theme.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }
    $zip->addFile($root . '/package/default-theme/theme.json', 'default-theme/theme.json');
    $zip->addFile($root . '/package/default-theme/readme.txt', 'default-theme/readme.txt');
    $zip->close();

    $installer = new ThemePackageInstaller(
        filesystem: $filesystem,
        uploadedZipArchive: new UploadedZipArchive($filesystem, new UploadedZipInspector()),
        themesPath: $root . '/themes',
    );

    $result = $installer->install([
        'error' => UPLOAD_ERR_OK,
        'name' => 'theme.zip',
        'tmp_name' => $zipPath,
        'size' => filesize($zipPath),
    ]);

    if ($result->themeDirectoryName !== 'default-theme') {
        return false;
    }

    if (!is_file($root . '/themes/default-theme/theme.json')) {
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
