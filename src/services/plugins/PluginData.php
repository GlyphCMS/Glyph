<?php

declare(strict_types=1);

namespace Glyph\services\plugins;

final class PluginData
{
    /**
     * @param list<string> $requiredPlugins
     * @param array<string, mixed> $manifest
     */
    public function __construct(
        public readonly string $name,
        public readonly string $directoryName,
        public readonly string $path,
        public readonly string $version,
        public readonly string $author,
        public readonly string $description,
        public readonly ?string $homepageUrl,
        public readonly array $requiredPlugins,
        public readonly bool $isEnabled,
        public readonly array $manifest,
    ) {
    }
}
