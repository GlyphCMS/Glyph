<?php

declare(strict_types=1);

$root = sys_get_temp_dir() . '/glyph-runtime-config-' . bin2hex(random_bytes(6));

try {
    mkdir($root . '/bootstrap', 0755, true);
    mkdir($root . '/config', 0755, true);
    mkdir($root . '/data/system', 0755, true);
    mkdir($root . '/src/domain/shared', 0755, true);

    copy(dirname(__DIR__, 2) . '/bootstrap/config.php', $root . '/bootstrap/config.php');
    copy(dirname(__DIR__, 2) . '/bootstrap/autoload.php', $root . '/bootstrap/autoload.php');
    copy(dirname(__DIR__, 2) . '/src/domain/shared/AppPaths.php', $root . '/src/domain/shared/AppPaths.php');

    file_put_contents($root . '/config/app.php', "<?php\ndeclare(strict_types=1);\nreturn ['timezone' => 'UTC'];\n");
    file_put_contents($root . '/config/auth.php', "<?php\ndeclare(strict_types=1);\nreturn [];\n");
    file_put_contents($root . '/config/cache.php', "<?php\ndeclare(strict_types=1);\nreturn ['driver' => 'file', 'apcu_enabled' => false];\n");
    file_put_contents($root . '/config/mail.php', "<?php\ndeclare(strict_types=1);\nreturn ['transport' => 'php_mail', 'from_name' => 'Glyph', 'from_email' => ''];\n");
    file_put_contents($root . '/config/maintenance.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => false, 'message' => 'Maintenance'];\n");
    file_put_contents($root . '/config/plugins.php', "<?php\ndeclare(strict_types=1);\nreturn ['enabled' => []];\n");
    file_put_contents($root . '/config/navigation.php', "<?php\ndeclare(strict_types=1);\nreturn ['menus' => ['primary' => [], 'footer' => []]];\n");
    file_put_contents($root . '/config/updater.php', "<?php\ndeclare(strict_types=1);\nreturn ['channel' => 'stable', 'release_manifest_url' => '', 'allow_prerelease' => false];\n");
    file_put_contents($root . '/config/versioning.php', "<?php\ndeclare(strict_types=1);\nreturn ['schema_version' => '1.0.0', 'auto_run_migrations' => true];\n");
    file_put_contents($root . '/config/media.php', "<?php\ndeclare(strict_types=1);\nreturn [];\n");
    file_put_contents($root . '/config/site.php', "<?php\ndeclare(strict_types=1);\nreturn ['site_name' => 'Glyph Site', 'site_url' => '', 'active_theme' => 'default'];\n");
    file_put_contents($root . '/config/paths.php', "<?php\ndeclare(strict_types=1);\nreturn ['data_system' => 'data/system'];\n");

    file_put_contents($root . '/data/system/site.php', "<?php\ndeclare(strict_types=1);\nreturn ['site_name' => 'Glyph Demo'];\n");
    file_put_contents($root . '/data/system/cache.php', "<?php\ndeclare(strict_types=1);\nreturn ['driver' => 'apcu', 'apcu_enabled' => true];\n");
    file_put_contents($root . '/data/system/mail.php', "<?php\ndeclare(strict_types=1);\nreturn ['from_email' => 'demo@example.com'];\n");
    file_put_contents($root . '/data/system/generated.php', "<?php\ndeclare(strict_types=1);\nreturn ['is_installed' => true, 'site_url' => 'https://installed.example.com', 'secret_key' => 'abc'];\n");

    $bootstrap = require $root . '/bootstrap/config.php';

    if (($bootstrap['config']['site']['site_name'] ?? '') !== 'Glyph Demo') {
        return false;
    }

    if (($bootstrap['config']['cache']['driver'] ?? '') !== 'apcu') {
        return false;
    }

    if (($bootstrap['config']['mail']['from_email'] ?? '') !== 'demo@example.com') {
        return false;
    }

    if (($bootstrap['config']['generated']['is_installed'] ?? false) !== true) {
        return false;
    }

    if (($bootstrap['config']['site']['site_url'] ?? '') !== 'https://installed.example.com') {
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

