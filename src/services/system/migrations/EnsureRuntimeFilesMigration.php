<?php

declare(strict_types=1);

namespace Glyph\services\system\migrations;

use Glyph\services\system\MigrationContext;
use Glyph\services\system\MigrationInterface;

final class EnsureRuntimeFilesMigration implements MigrationInterface
{
    public function id(): string
    {
        return '2026_03_08_000002_ensure_runtime_files';
    }

    public function description(): string
    {
        return 'Ensure runtime directories and updater state files exist.';
    }

    public function apply(MigrationContext $context): void
    {
        $directories = [
            $context->paths['data_cache'],
            $context->paths['data_indexes'],
            $context->paths['data_media'],
            $context->paths['data_redirects'],
            $context->paths['data_sessions'],
            $context->paths['data_system'],
            $context->paths['data_users'],
            $context->paths['storage'],
            $context->paths['storage_logs'],
        ];

        foreach ($directories as $directory) {
            $context->filesystem->ensureDirectoryExists($directory);
        }
    }
}
