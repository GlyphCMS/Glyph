<?php

declare(strict_types=1);

use Glyph\services\system\UpdateManifestFetcher;

$root = sys_get_temp_dir() . '/glyph-update-manifest-' . bin2hex(random_bytes(6));
mkdir($root, 0755, true);

try {
    $manifestPath = $root . '/manifest.json';
    file_put_contents($manifestPath, json_encode([
        'name' => 'Glyph',
        'latest' => [
            'version' => '0.2.0',
            'published_at' => '2026-03-08T00:00:00Z',
            'package_url' => 'https://example.com/glyph.zip',
            'min_php_version' => '8.1.0',
        ],
    ], JSON_PRETTY_PRINT));

    $fetcher = new UpdateManifestFetcher();
    $manifest = $fetcher->fetch('file://' . $manifestPath);

    return (($manifest['latest']['version'] ?? '') === '0.2.0');
} finally {
    if (is_file($root . '/manifest.json')) {
        unlink($root . '/manifest.json');
    }
    if (is_dir($root)) {
        rmdir($root);
    }
}
