<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdateSettings
{
    public function __construct(
        public readonly string $channel,
        public readonly string $releaseManifestUrl,
        public readonly bool $allowPrerelease,
    ) {
    }
}
