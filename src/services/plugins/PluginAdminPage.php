<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

final class PluginAdminPage
{
    /**
     * @param callable $renderer
     */
    public function __construct(
        public readonly string $pluginDirectoryName,
        public readonly string $pageKey,
        public readonly string $title,
        public readonly string $description,
        public readonly mixed $renderer,
    ) {
    }
}
