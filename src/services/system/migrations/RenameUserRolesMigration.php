<?php

declare(strict_types=1);

namespace Glyph\services\system\migrations;

use Glyph\services\system\MigrationContext;
use Glyph\services\system\MigrationInterface;

final class RenameUserRolesMigration implements MigrationInterface
{
    public function id(): string
    {
        return '2026_03_12_000003_rename_user_roles';
    }

    public function description(): string
    {
        return 'Rename legacy user roles to Reader, Author, Editor, and Administrator.';
    }

    public function apply(MigrationContext $context): void
    {
        $userFiles = glob($context->paths['data_users'] . '/*.json');

        if ($userFiles === false) {
            throw new \RuntimeException('Failed to scan user records for role migration.');
        }

        foreach ($userFiles as $userFile) {
            if (!is_string($userFile)) {
                continue;
            }

            $contents = $context->filesystem->readFile($userFile);
            $decoded = json_decode($contents, true);

            if (!is_array($decoded)) {
                throw new \RuntimeException(sprintf('Invalid user record JSON: %s', $userFile));
            }

            $role = $decoded['role'] ?? null;
            if (!is_string($role) || $role === '') {
                continue;
            }

            $mappedRole = match ($role) {
                'editor' => 'author',
                'administrator' => 'editor',
                'owner' => 'administrator',
                default => $role,
            };

            if ($mappedRole === $role) {
                continue;
            }

            $decoded['role'] = $mappedRole;
            $json = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (!is_string($json) || $json === '') {
                throw new \RuntimeException(sprintf('Failed to encode migrated user record: %s', $userFile));
            }

            $context->filesystem->writeFile($userFile, $json . PHP_EOL);
        }
    }
}
