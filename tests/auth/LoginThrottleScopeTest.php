<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\services\auth\LoginThrottle;

$root = sys_get_temp_dir() . '/glyph-throttle-scope-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root);

    $login = new LoginThrottle($filesystem, $root, 2, 900, 'login');
    $forgot = new LoginThrottle($filesystem, $root, 2, 900, 'forgot-password');

    $login->recordFailure('user@example.com', '127.0.0.1');

    if ($forgot->isBlocked('user@example.com', '127.0.0.1')) {
        return false;
    }

    $forgot->recordFailure('user@example.com', '127.0.0.1');
    $forgot->recordFailure('user@example.com', '127.0.0.1');

    if (!$forgot->isBlocked('user@example.com', '127.0.0.1')) {
        return false;
    }

    if ($login->isBlocked('user@example.com', '127.0.0.1')) {
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
