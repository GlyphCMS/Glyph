<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

final class PluginInstallResult
{
    public function __construct(
        public readonly string $pluginDirectoryName,
        public readonly string $pluginName,
    ) {
    }
}
