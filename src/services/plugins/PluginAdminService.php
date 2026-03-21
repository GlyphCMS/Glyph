<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;

final class PluginAdminService
{
    /**
     * @param array<string, mixed> $pluginsConfig
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly PhpConfigWriter $configWriter,
        private readonly PluginResolver $pluginResolver,
        private readonly PluginSettingsStore $pluginSettingsStore,
        private readonly string $systemPath,
        private readonly array $pluginsConfig,
    ) {
    }

    public function enable(string $pluginDirectoryName): void
    {
        $plugin = $this->requirePlugin($pluginDirectoryName);
        $enabled = $this->currentEnabledPlugins();

        foreach ($plugin->requiredPlugins as $requiredPlugin) {
            if (!in_array($requiredPlugin, $enabled, true)) {
                throw new \RuntimeException(sprintf(
                    'Plugin "%s" requires "%s" to be enabled first.',
                    $plugin->directoryName,
                    $requiredPlugin
                ));
            }
        }

        if (!in_array($plugin->directoryName, $enabled, true)) {
            $enabled[] = $plugin->directoryName;
            sort($enabled);
            $this->writeEnabledPlugins($enabled);
            $this->runLifecycleFile($plugin, 'enable.php');
        }
    }

    public function disable(string $pluginDirectoryName): void
    {
        $plugin = $this->requirePlugin($pluginDirectoryName);
        $enabled = $this->currentEnabledPlugins();

        foreach ($this->pluginResolver->listPlugins() as $candidate) {
            if (!$candidate->isEnabled || $candidate->directoryName === $plugin->directoryName) {
                continue;
            }

            if (in_array($plugin->directoryName, $candidate->requiredPlugins, true)) {
                throw new \RuntimeException(sprintf(
                    'Plugin "%s" cannot be disabled while "%s" depends on it.',
                    $plugin->directoryName,
                    $candidate->directoryName
                ));
            }
        }

        $enabled = array_values(array_filter(
            $enabled,
            static fn (string $value): bool => $value !== $plugin->directoryName
        ));

        $this->writeEnabledPlugins($enabled);
        $this->runLifecycleFile($plugin, 'disable.php');
    }

    public function delete(string $pluginDirectoryName): void
    {
        $plugin = $this->requirePlugin($pluginDirectoryName);

        if ($plugin->isEnabled) {
            throw new \RuntimeException('Disable the plugin before deleting it.');
        }

        foreach ($this->pluginResolver->listPlugins() as $candidate) {
            if ($candidate->directoryName === $plugin->directoryName) {
                continue;
            }

            if (in_array($plugin->directoryName, $candidate->requiredPlugins, true)) {
                throw new \RuntimeException(sprintf(
                    'Plugin "%s" cannot be deleted because "%s" depends on it.',
                    $plugin->directoryName,
                    $candidate->directoryName
                ));
            }
        }

        $this->runLifecycleFile($plugin, 'uninstall.php');
        $this->pluginSettingsStore->deleteSettings($plugin->directoryName);
        $this->filesystem->deleteDirectoryRecursively($plugin->path);
    }

    private function requirePlugin(string $pluginDirectoryName): PluginData
    {
        if (preg_match('/^[a-z0-9_-]+$/', $pluginDirectoryName) !== 1) {
            throw new \RuntimeException('Invalid plugin identifier.');
        }

        $plugin = $this->pluginResolver->find($pluginDirectoryName);

        if ($plugin === null) {
            throw new \RuntimeException('Plugin not found.');
        }

        return $plugin;
    }

    /**
     * @return list<string>
     */
    private function currentEnabledPlugins(): array
    {
        $enabled = $this->pluginsConfig['enabled'] ?? [];
        $normalized = [];

        if (is_array($enabled)) {
            foreach ($enabled as $value) {
                if (is_string($value) && preg_match('/^[a-z0-9_-]+$/', $value) === 1) {
                    $normalized[] = $value;
                }
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param list<string> $enabled
     */
    private function writeEnabledPlugins(array $enabled): void
    {
        $this->configWriter->write(
            $this->systemPath . '/plugins.php',
            [
                'enabled' => $enabled,
            ],
        );
    }

    private function runLifecycleFile(PluginData $plugin, string $fileName): void
    {
        $path = $plugin->path . '/' . $fileName;

        if (!$this->filesystem->isFile($path)) {
            return;
        }

        $callable = require $path;

        if (!is_callable($callable)) {
            throw new \RuntimeException(sprintf(
                'Plugin lifecycle file "%s" for "%s" must return a callable.',
                $fileName,
                $plugin->directoryName
            ));
        }

        $callable(new PluginLifecycleContext($plugin, $this->pluginSettingsStore));
    }
}
