<?php

declare(strict_types=1);

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;
use Glyph\services\plugins\PluginAdminService;
use Glyph\services\plugins\PluginResolver;
use Glyph\services\plugins\PluginSettingsStore;

$root = sys_get_temp_dir() . '/glyph-plugin-admin-' . bin2hex(random_bytes(6));
$filesystem = new LocalFilesystem();

try {
    $filesystem->ensureDirectoryExists($root . '/plugins/base-tools');
    $filesystem->ensureDirectoryExists($root . '/plugins/hello');
    $filesystem->ensureDirectoryExists($root . '/system');

    file_put_contents($root . '/plugins/base-tools/plugin.json', json_encode(['name' => 'Base Tools'], JSON_PRETTY_PRINT));
    file_put_contents($root . '/plugins/hello/plugin.json', json_encode([
        'name' => 'Hello',
        'required_plugins' => ['base-tools'],
    ], JSON_PRETTY_PRINT));
    file_put_contents($root . '/plugins/hello/enable.php', <<<'PHP'
<?php
declare(strict_types=1);
use Glyph\services\plugins\PluginLifecycleContext;
return static function (PluginLifecycleContext $plugin): void {
    $plugin->saveSettings(['enabled_once' => true]);
};
PHP);
    file_put_contents($root . '/plugins/hello/uninstall.php', <<<'PHP'
<?php
declare(strict_types=1);
use Glyph\services\plugins\PluginLifecycleContext;
return static function (PluginLifecycleContext $plugin): void {
    $plugin->deleteSettings();
};
PHP);

    $store = new PluginSettingsStore(new PhpConfigWriter($filesystem), $root . '/system/plugin-settings.php');

    $resolverMissingDependency = new PluginResolver($filesystem, $root . '/plugins', ['enabled' => []]);
    $serviceMissingDependency = new PluginAdminService(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        pluginResolver: $resolverMissingDependency,
        pluginSettingsStore: $store,
        systemPath: $root . '/system',
        pluginsConfig: ['enabled' => []],
    );

    try {
        $serviceMissingDependency->enable('hello');
        return false;
    } catch (RuntimeException $runtimeException) {
        // expected
    }

    $resolver = new PluginResolver($filesystem, $root . '/plugins', ['enabled' => ['base-tools']]);
    $service = new PluginAdminService(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        pluginResolver: $resolver,
        pluginSettingsStore: $store,
        systemPath: $root . '/system',
        pluginsConfig: ['enabled' => ['base-tools']],
    );

    $service->enable('hello');
    $config = require $root . '/system/plugins.php';
    if (($config['enabled'] ?? []) !== ['base-tools', 'hello']) {
        return false;
    }

    $settings = require $root . '/system/plugin-settings.php';
    if ((($settings['hello'] ?? [])['enabled_once'] ?? false) !== true) {
        return false;
    }

    $resolverDelete = new PluginResolver($filesystem, $root . '/plugins', ['enabled' => ['base-tools']]);
    $serviceDelete = new PluginAdminService(
        filesystem: $filesystem,
        configWriter: new PhpConfigWriter($filesystem),
        pluginResolver: $resolverDelete,
        pluginSettingsStore: $store,
        systemPath: $root . '/system',
        pluginsConfig: ['enabled' => ['base-tools']],
    );

    $serviceDelete->delete('hello');

    if (is_dir($root . '/plugins/hello')) {
        return false;
    }

    $settingsAfterDelete = require $root . '/system/plugin-settings.php';
    if (array_key_exists('hello', $settingsAfterDelete)) {
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
