<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\filesystem\UploadedZipArchive;
use Glyph\adapters\filesystem\UploadedZipInspector;
use Glyph\services\plugins\PluginPackageInstaller;

if (!class_exists('ZipArchive')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-plugin-package-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/plugins');
    $filesystem->ensureDirectoryExists($root . '/package/demo-plugin');
    file_put_contents($root . '/package/demo-plugin/plugin.json', json_encode([
        'name' => 'Demo Plugin',
        'version' => '1.0.0',
        'required_plugins' => ['base-tools'],
    ], JSON_PRETTY_PRINT));
    file_put_contents($root . '/package/demo-plugin/bootstrap.php', "<?php\nreturn static function (): void {};\n");
    file_put_contents($root . '/package/demo-plugin/install.php', "<?php\nreturn static function (): void {};\n");

    $zipPath = $root . '/plugin.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return false;
    }
    $zip->addFile($root . '/package/demo-plugin/plugin.json', 'demo-plugin/plugin.json');
    $zip->addFile($root . '/package/demo-plugin/bootstrap.php', 'demo-plugin/bootstrap.php');
    $zip->addFile($root . '/package/demo-plugin/install.php', 'demo-plugin/install.php');
    $zip->close();

    $installer = new PluginPackageInstaller(
        filesystem: $filesystem,
        uploadedZipArchive: new UploadedZipArchive($filesystem, new UploadedZipInspector()),
        pluginsPath: $root . '/plugins',
    );

    $result = $installer->install([
        'error' => UPLOAD_ERR_OK,
        'name' => 'plugin.zip',
        'tmp_name' => $zipPath,
        'size' => filesize($zipPath),
    ]);

    if ($result->pluginDirectoryName !== 'demo-plugin') {
        return false;
    }

    if (!is_file($root . '/plugins/demo-plugin/plugin.json')) {
        return false;
    }

    if (!is_file($root . '/plugins/demo-plugin/bootstrap.php')) {
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
