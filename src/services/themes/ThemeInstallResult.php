<?php

declare(strict_types=1);

namespace Glyph\services\themes;

final class ThemeInstallResult
{
    public function __construct(
        public readonly string $themeDirectoryName,
        public readonly string $themeName,
    ) {
    }
}
