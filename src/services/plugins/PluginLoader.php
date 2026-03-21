<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

use Glyph\adapters\security\CsrfTokenManager;

final class PluginLoader
{
    public function __construct(
        private readonly PluginResolver $pluginResolver,
        private readonly HookManager $hookManager,
        private readonly PluginSettingsStore $pluginSettingsStore,
        private readonly CsrfTokenManager $csrfTokenManager,
    ) {
    }

    public function loadEnabledPlugins(): void
    {
        foreach ($this->pluginResolver->listPlugins() as $plugin) {
            if (!$plugin->isEnabled) {
                continue;
            }

            $bootstrapPath = $plugin->path . '/bootstrap.php';
            if (!is_file($bootstrapPath)) {
                continue;
            }

            $bootstrap = require $bootstrapPath;
            $context = new PluginContext($plugin, $this->hookManager, $this->pluginSettingsStore, $this->csrfTokenManager);

            if (is_callable($bootstrap)) {
                $bootstrap($context);
                continue;
            }

            if (is_object($bootstrap) && method_exists($bootstrap, 'register')) {
                $bootstrap->register($context);
                continue;
            }

            throw new \RuntimeException(sprintf('Invalid plugin bootstrap for %s.', $plugin->directoryName));
        }
    }
}
