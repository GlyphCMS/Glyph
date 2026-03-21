<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class MaintenanceSettings
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $message,
    ) {
    }
}
