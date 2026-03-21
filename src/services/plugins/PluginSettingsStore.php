<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\storage\PhpConfigWriter;

final class PluginSettingsStore
{
    public function __construct(
        private readonly PhpConfigWriter $configWriter,
        private readonly string $settingsPath,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function settingsFor(string $pluginDirectoryName): array
    {
        $all = $this->allSettings();
        $settings = $all[$pluginDirectoryName] ?? [];

        return is_array($settings) ? $settings : [];
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function saveSettings(string $pluginDirectoryName, array $settings): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $pluginDirectoryName) !== 1) {
            throw new \RuntimeException('Invalid plugin identifier.');
        }

        $all = $this->allSettings();
        $all[$pluginDirectoryName] = $settings;
        ksort($all);

        $this->configWriter->write($this->settingsPath, $all);
    }

    public function deleteSettings(string $pluginDirectoryName): void
    {
        if (preg_match('/^[a-z0-9_-]+$/', $pluginDirectoryName) !== 1) {
            throw new \RuntimeException('Invalid plugin identifier.');
        }

        $all = $this->allSettings();
        unset($all[$pluginDirectoryName]);
        ksort($all);

        $this->configWriter->write($this->settingsPath, $all);
    }

    /**
     * @return array<string, mixed>
     */
    private function allSettings(): array
    {
        if (!is_file($this->settingsPath)) {
            return [];
        }

        $loaded = require $this->settingsPath;

        if (!is_array($loaded)) {
            throw new \RuntimeException('Invalid plugin settings file.');
        }

        return $loaded;
    }
}
