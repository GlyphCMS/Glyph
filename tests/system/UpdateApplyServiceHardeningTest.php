<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;
use Glyph\adapters\filesystem\UploadedZipInspector;
use Glyph\services\system\SystemBackupManager;
use Glyph\services\system\UpdateApplyService;

if (!class_exists('ZipArchive')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-update-hardening-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/install/content');
    $filesystem->ensureDirectoryExists($root . '/install/data');
    $filesystem->ensureDirectoryExists($root . '/install/uploads');
    $filesystem->ensureDirectoryExists($root . '/install/storage/backups');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/bootstrap');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/config');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/src');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/themes/default');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/malicious');

    file_put_contents($root . '/package/Glyph/bootstrap/app.php', "<?php return [];\n");
    file_put_contents($root . '/package/Glyph/bootstrap/config.php', "<?php return [];\n");
    file_put_contents($root . '/package/Glyph/config/app.php', "<?php return ['version' => '1.0.0'];\n");
    file_put_contents($root . '/package/Glyph/src/Demo.php', "<?php\n");
    file_put_contents($root . '/package/Glyph/themes/default/theme.json', "{}");
    file_put_contents($root . '/package/Glyph/malicious/bad.txt', "bad");

    $zipPath = $root . '/bad-update.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/package/Glyph', FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $fullPath = $item->getPathname();
        $relative = substr($fullPath, strlen($root . '/package/'));
        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($fullPath, $relative);
        }
    }
    $zip->close();

    $backupManager = new SystemBackupManager(
        filesystem: $filesystem,
        rootPath: $root . '/install',
        backupPath: $root . '/install/storage/backups',
        paths: [
            'content' => $root . '/install/content',
            'data' => $root . '/install/data',
            'uploads' => $root . '/install/uploads',
            'themes' => $root . '/install/themes',
            'plugins' => $root . '/install/plugins',
            'config' => $root . '/install/config',
        ],
    );

    $service = new UpdateApplyService(
        filesystem: $filesystem,
        uploadedZipArchive: new UploadedZipArchive($filesystem, new UploadedZipInspector()),
        systemBackupManager: $backupManager,
        installRootPath: $root . '/install',
        systemPath: $root . '/install/data/system',
    );

    try {
        $service->apply([
            'error' => UPLOAD_ERR_OK,
            'name' => 'bad-update.zip',
            'tmp_name' => $zipPath,
            'size' => filesize($zipPath),
        ]);
        return false;
    } catch (RuntimeException $runtimeException) {
        return str_contains($runtimeException->getMessage(), 'unexpected top-level path');
    }
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
