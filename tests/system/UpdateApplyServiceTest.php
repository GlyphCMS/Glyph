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

$root = sys_get_temp_dir() . '/glyph-update-apply-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/app/bootstrap');
    $filesystem->ensureDirectoryExists($root . '/app/config');
    $filesystem->ensureDirectoryExists($root . '/app/src');
    $filesystem->ensureDirectoryExists($root . '/app/themes/default');
    $filesystem->ensureDirectoryExists($root . '/app/content');
    $filesystem->ensureDirectoryExists($root . '/app/data/system');
    $filesystem->ensureDirectoryExists($root . '/app/uploads');
    $filesystem->ensureDirectoryExists($root . '/backup');

    file_put_contents($root . '/app/bootstrap/app.php', "<?php\n");
    file_put_contents($root . '/app/bootstrap/config.php', "<?php\n");
    file_put_contents($root . '/app/config/app.php', "<?php\nreturn ['version' => '1.0.0'];\n");
    file_put_contents($root . '/app/src/Keep.php', "<?php\n");
    file_put_contents($root . '/app/themes/default/theme.json', "{}");
    file_put_contents($root . '/app/content/preserved.txt', "keep");
    file_put_contents($root . '/app/data/system/local.php', "keep");
    file_put_contents($root . '/app/uploads/file.txt', "keep");

    $filesystem->ensureDirectoryExists($root . '/package/Glyph/bootstrap');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/config');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/src/NewDir');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/themes/default');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/content');
    file_put_contents($root . '/package/Glyph/bootstrap/app.php', "<?php\n// updated\n");
    file_put_contents($root . '/package/Glyph/bootstrap/config.php', "<?php\n// updated\n");
    file_put_contents($root . '/package/Glyph/config/app.php', "<?php\nreturn ['version' => '2.0.0'];\n");
    file_put_contents($root . '/package/Glyph/src/NewDir/New.php', "<?php\n");
    file_put_contents($root . '/package/Glyph/themes/default/theme.json', "{\"name\":\"Default\"}");
    file_put_contents($root . '/package/Glyph/content/should-skip.txt', "skip");

    $zipPath = $root . '/update.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/package', FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $path = $item->getPathname();
        $relative = ltrim(str_replace($root . '/package/', '', $path), '/');
        if ($item->isDir()) {
            $zip->addEmptyDir($relative);
        } else {
            $zip->addFile($path, $relative);
        }
    }
    $zip->close();

    $backupManager = new SystemBackupManager(
        filesystem: $filesystem,
        rootPath: $root . '/app',
        backupPath: $root . '/backup',
        paths: [
            'content' => $root . '/app/content',
            'data' => $root . '/app/data',
            'uploads' => $root . '/app/uploads',
            'themes' => $root . '/app/themes',
            'plugins' => $root . '/app/plugins',
            'config' => $root . '/app/config',
        ],
    );

    $service = new UpdateApplyService(
        filesystem: $filesystem,
        uploadedZipArchive: new UploadedZipArchive($filesystem, new UploadedZipInspector()),
        systemBackupManager: $backupManager,
        installRootPath: $root . '/app',
        systemPath: $root . '/app/data/system',
    );

    $result = $service->apply([
        'error' => UPLOAD_ERR_OK,
        'name' => 'update.zip',
        'tmp_name' => $zipPath,
        'size' => filesize($zipPath),
    ]);

    if ($result->appliedFileCount < 4) {
        return false;
    }

    if (!is_file($root . '/app/src/NewDir/New.php')) {
        return false;
    }

    if (file_get_contents($root . '/app/content/preserved.txt') !== 'keep') {
        return false;
    }

    if (!is_file($root . '/backup/' . basename($result->backupArchivePath))) {
        return false;
    }

    $state = require $root . '/app/data/system/updater-last-apply.php';
    if (($state['detected_version'] ?? '') !== '2.0.0') {
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
