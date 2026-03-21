<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdatePackageValidationResult
{
    /**
     * @param list<string> $detectedTopLevelEntries
     * @param list<string> $requiredEntriesFound
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $packageName,
        public readonly bool $isValid,
        public readonly ?string $glyphRoot,
        public readonly array $detectedTopLevelEntries,
        public readonly array $requiredEntriesFound,
        public readonly array $warnings,
    ) {
    }
}
