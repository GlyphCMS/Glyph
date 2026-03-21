<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\filesystem\LocalFilesystem;

final class PluginResolver
{
    /**
     * @param array<string, mixed> $pluginsConfig
     */
    public function __construct(
        private readonly LocalFilesystem $filesystem,
        private readonly string $pluginsPath,
        private readonly array $pluginsConfig,
    ) {
    }

    /**
     * @return list<PluginData>
     */
    public function listPlugins(): array
    {
        $plugins = [];

        foreach ($this->filesystem->listDirectories($this->pluginsPath) as $directoryPath) {
            $directoryName = basename($directoryPath);

            if (!is_string($directoryName) || preg_match('/^[a-z0-9_-]+$/', $directoryName) !== 1) {
                continue;
            }

            $manifestPath = $directoryPath . '/plugin.json';
            $manifest = [];

            if ($this->filesystem->isFile($manifestPath)) {
                $decoded = json_decode($this->filesystem->readFile($manifestPath), true);

                if (!is_array($decoded)) {
                    throw new \RuntimeException(sprintf('Invalid plugin manifest: %s', $manifestPath));
                }

                $manifest = $decoded;
            }

            $plugins[] = new PluginData(
                name: $this->manifestString($manifest, 'name', $directoryName),
                directoryName: $directoryName,
                path: $directoryPath,
                version: $this->manifestString($manifest, 'version', '0.1.0'),
                author: $this->manifestString($manifest, 'author', 'Unknown'),
                description: $this->manifestString($manifest, 'description', ''),
                homepageUrl: $this->manifestUrl($manifest, 'homepage'),
                requiredPlugins: $this->manifestPluginList($manifest, 'required_plugins'),
                isEnabled: in_array($directoryName, $this->enabledPluginNames(), true),
                manifest: $manifest,
            );
        }

        usort(
            $plugins,
            static fn (PluginData $left, PluginData $right): int => strcmp($left->name, $right->name)
        );

        return $plugins;
    }

    public function find(string $pluginDirectoryName): ?PluginData
    {
        foreach ($this->listPlugins() as $plugin) {
            if ($plugin->directoryName === $pluginDirectoryName) {
                return $plugin;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function enabledPluginNames(): array
    {
        $enabled = $this->pluginsConfig['enabled'] ?? [];

        if (!is_array($enabled)) {
            return [];
        }

        $names = [];

        foreach ($enabled as $value) {
            if (is_string($value) && preg_match('/^[a-z0-9_-]+$/', $value) === 1) {
                $names[] = $value;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function manifestString(array $manifest, string $key, string $default): string
    {
        $value = $manifest[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function manifestUrl(array $manifest, string $key): ?string
    {
        $value = $manifest[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : null;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function manifestPluginList(array $manifest, string $key): array
    {
        $value = $manifest[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        $plugins = [];

        foreach ($value as $item) {
            if (is_string($item) && preg_match('/^[a-z0-9_-]+$/', $item) === 1) {
                $plugins[] = $item;
            }
        }

        return array_values(array_unique($plugins));
    }
}
