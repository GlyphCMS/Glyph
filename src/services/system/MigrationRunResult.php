<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class MigrationRunResult
{
    /**
     * @param list<string> $appliedMigrationIds
     * @param list<string> $messages
     */
    public function __construct(
        public readonly string $appVersion,
        public readonly string $schemaVersion,
        public readonly array $appliedMigrationIds,
        public readonly array $messages,
        public readonly bool $wasAlreadyCurrent,
    ) {
    }
}
