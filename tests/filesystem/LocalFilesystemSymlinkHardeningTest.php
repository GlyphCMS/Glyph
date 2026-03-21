<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;

if (!function_exists('symlink')) {
    return true;
}

$root = sys_get_temp_dir() . '/glyph-filesystem-links-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/source');
    $filesystem->ensureDirectoryExists($root . '/target');
    file_put_contents($root . '/target/sentinel.txt', 'keep');

    if (@symlink($root . '/target', $root . '/source/linked-target') !== true) {
        return true;
    }

    $filesystem->deleteDirectoryRecursively($root . '/source');

    if (!is_file($root . '/target/sentinel.txt')) {
        return false;
    }

    $filesystem->ensureDirectoryExists($root . '/copy-source');

    if (@symlink($root . '/target/sentinel.txt', $root . '/copy-source/linked-file.txt') !== true) {
        return true;
    }

    try {
        $filesystem->copyDirectoryRecursively($root . '/copy-source', $root . '/copy-destination');
        return false;
    } catch (RuntimeException $runtimeException) {
        return str_contains($runtimeException->getMessage(), 'symlinked');
    }
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();

            if (is_link($path) || !$item->isDir()) {
                unlink($path);
                continue;
            }

            rmdir($path);
        }

        rmdir($root);
    }
}
