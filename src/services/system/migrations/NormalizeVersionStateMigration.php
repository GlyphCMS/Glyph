<?php

declare(strict_types=1);

namespace Glyph\services\system\migrations;

use Glyph\services\system\MigrationContext;
use Glyph\services\system\MigrationInterface;

final class NormalizeVersionStateMigration implements MigrationInterface
{
    public function id(): string
    {
        return '2026_03_08_000001_normalize_version_state';
    }

    public function description(): string
    {
        return 'Initialize normalized version state tracking.';
    }

    public function apply(MigrationContext $context): void
    {
        $context->filesystem->ensureDirectoryExists($context->paths['data_system']);
    }
}
