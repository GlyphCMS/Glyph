<?php

declare(strict_types=1);

namespace Glyph\services\system;

final class UpdateApplyResult
{
    /**
     * @param list<string> $warnings
     */
    public function __construct(
        public readonly string $backupArchivePath,
        public readonly int $appliedFileCount,
        public readonly int $ensuredDirectoryCount,
        public readonly ?string $packageSha256,
        public readonly ?string $detectedVersion,
        public readonly array $warnings,
    ) {
    }
}
