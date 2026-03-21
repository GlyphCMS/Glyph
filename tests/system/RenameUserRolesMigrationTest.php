<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\system\MigrationContext;
use Glyph\services\system\migrations\RenameUserRolesMigration;

$root = sys_get_temp_dir() . '/glyph-role-migration-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/data/users');

    $writeUser = static function (string $path, string $role) use ($filesystem): void {
        $filesystem->writeFile($path, json_encode([
            'id' => basename($path, '.json'),
            'email' => basename($path, '.json') . '@example.com',
            'display_name' => '',
            'password_hash' => 'hash',
            'role' => $role,
            'is_active' => true,
            'created_at' => '2026-03-12T00:00:00+00:00',
            'updated_at' => '2026-03-12T00:00:00+00:00',
            'last_login_at' => null,
            'remember_tokens' => [],
            'password_reset_tokens' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    };

    $writeUser($root . '/data/users/reader.json', 'reader');
    $writeUser($root . '/data/users/editor.json', 'editor');
    $writeUser($root . '/data/users/administrator.json', 'administrator');
    $writeUser($root . '/data/users/owner.json', 'owner');

    $migration = new RenameUserRolesMigration();
    $migration->apply(new MigrationContext(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        paths: [
            'data_users' => $root . '/data/users',
        ],
    ));

    $readRole = static function (string $path) use ($filesystem): ?string {
        $decoded = json_decode($filesystem->readFile($path), true);
        return is_array($decoded) && is_string($decoded['role'] ?? null) ? $decoded['role'] : null;
    };

    return $readRole($root . '/data/users/reader.json') === 'reader'
        && $readRole($root . '/data/users/editor.json') === 'author'
        && $readRole($root . '/data/users/administrator.json') === 'editor'
        && $readRole($root . '/data/users/owner.json') === 'administrator';
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
