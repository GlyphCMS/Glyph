<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\security\CsrfTokenManager;

final class PluginContext
{
    public function __construct(
        private readonly PluginData $plugin,
        private readonly HookManager $hookManager,
        private readonly PluginSettingsStore $pluginSettingsStore,
        private readonly CsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function plugin(): PluginData
    {
        return $this->plugin;
    }

    public function addAction(string $hookName, callable $listener): void
    {
        $this->hookManager->addAction($hookName, $listener);
    }

    public function addFilter(string $hookName, callable $listener): void
    {
        $this->hookManager->addFilter($hookName, $listener);
    }

    public function addSlot(string $slotName, callable $renderer): void
    {
        $this->hookManager->addSlot($slotName, $renderer);
    }

    public function registerAdminPage(string $pageKey, string $title, callable $renderer, string $description = ''): void
    {
        $normalizedPageKey = trim($pageKey);

        if ($normalizedPageKey === '' || preg_match('/^[a-z0-9_-]+$/', $normalizedPageKey) !== 1) {
            throw new \RuntimeException('Invalid plugin admin page key.');
        }

        $this->hookManager->registerAdminPage(
            new PluginAdminPage(
                pluginDirectoryName: $this->plugin->directoryName,
                pageKey: $normalizedPageKey,
                title: $title,
                description: $description,
                renderer: $renderer,
            )
        );
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

    public function csrfToken(string $formId): string
    {
        return $this->csrfTokenManager->token('plugin_' . $this->plugin->directoryName . '_' . $formId);
    }

    public function validateCsrfToken(string $formId, ?string $submittedToken): bool
    {
        return $this->csrfTokenManager->validate('plugin_' . $this->plugin->directoryName . '_' . $formId, $submittedToken);
    }
}
