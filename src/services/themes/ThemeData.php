<?php

declare(strict_types=1);

namespace Glyph\services\themes;

final class ThemeData
{
    /**
     * @param array<string, mixed> $manifest
     * @param array<string, string> $assets
     */
    public function __construct(
        public readonly string $name,
        public readonly string $directoryName,
        public readonly string $path,
        public readonly string $version,
        public readonly string $author,
        public readonly string $description,
        public readonly ?string $screenshotUrl,
        public readonly array $assets,
        public readonly array $manifest,
    ) {
    }
}
