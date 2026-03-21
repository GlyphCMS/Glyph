<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

final class PluginLifecycleContext
{
    public function __construct(
        private readonly PluginData $plugin,
        private readonly PluginSettingsStore $pluginSettingsStore,
    ) {
    }

    public function plugin(): PluginData
    {
        return $this->plugin;
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return $this->pluginSettingsStore->settingsFor($this->plugin->directoryName);
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function saveSettings(array $settings): void
    {
        $this->pluginSettingsStore->saveSettings($this->plugin->directoryName, $settings);
    }

    public function deleteSettings(): void
    {
        $this->pluginSettingsStore->deleteSettings($this->plugin->directoryName);
    }
}
