<?php

declare(strict_types=1);

namespace Glyph\services\system;

use Glyph\adapters\filesystem\LocalFilesystem;
use Glyph\adapters\storage\PhpConfigWriter;

final class MigrationContext
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        public readonly LocalFilesystem $filesystem,
        public readonly PhpConfigWriter $configWriter,
        public readonly array $paths,
    ) {
    }
}
