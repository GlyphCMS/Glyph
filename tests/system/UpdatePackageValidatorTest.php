<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;
use Glyph\adapters\filesystem\UploadedZipInspector;
use Glyph\services\system\UpdatePackageValidator;

if (!class_exists('ZipArchive')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-update-package-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/bootstrap');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/config');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/src');
    $filesystem->ensureDirectoryExists($root . '/package/Glyph/themes');
    file_put_contents($root . '/package/Glyph/bootstrap/app.php', "<?php
");
    file_put_contents($root . '/package/Glyph/bootstrap/config.php', "<?php
");
    file_put_contents($root . '/package/Glyph/config/app.php', "<?php
");
    file_put_contents($root . '/package/Glyph/content-placeholder.txt', 'x');

    $zipPath = $root . '/update.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }
    $zip->addFile($root . '/package/Glyph/bootstrap/app.php', 'Glyph/bootstrap/app.php');
    $zip->addFile($root . '/package/Glyph/bootstrap/config.php', 'Glyph/bootstrap/config.php');
    $zip->addFile($root . '/package/Glyph/config/app.php', 'Glyph/config/app.php');
    $zip->addEmptyDir('Glyph/src');
    $zip->addEmptyDir('Glyph/themes');
    $zip->addFile($root . '/package/Glyph/content-placeholder.txt', 'content/file.txt');
    $zip->close();

    $validator = new UpdatePackageValidator(new UploadedZipArchive($filesystem, new UploadedZipInspector()));
    $result = $validator->validate([
        'error' => UPLOAD_ERR_OK,
        'name' => 'update.zip',
        'tmp_name' => $zipPath,
        'size' => filesize($zipPath),
    ]);

    return $result->isValid === true && $result->glyphRoot === 'Glyph';
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir()) { rmdir($item->getPathname()); } else { unlink($item->getPathname()); }
        }
        rmdir($root);
    }
}
